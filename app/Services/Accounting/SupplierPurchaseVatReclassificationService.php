<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\SupplierPurchaseInvoice;
use App\Models\SupplierPurchaseVatReclassification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPurchaseVatReclassificationService
{
    public function reclassify(int $invoiceId, array $input, int $actorId): SupplierPurchaseVatReclassification
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.correct-supplier-purchases'), 403);
        $data = Validator::make($input, [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:4000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.line_id' => ['required', 'integer', 'distinct', 'exists:supplier_purchase_lines,id'],
            'allocations.*.amount_cents' => ['required', 'integer', 'min:1'],
            'allocations.*.remaining_inventory_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_cost_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Supporting rationale is required.']);
        }

        return DB::transaction(function () use ($invoiceId, $data, $reason, $actor): SupplierPurchaseVatReclassification {
            $candidate = SupplierPurchaseInvoice::query()->findOrFail($invoiceId);
            $chainIds = $candidate->correctionChainInvoiceIds();
            SupplierPurchaseInvoice::query()->lockForUpdate()->findOrFail($chainIds[0]);
            $invoice = SupplierPurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoiceId);
            if (SupplierPurchaseVatReclassification::query()->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['idempotency_key' => 'This purchase VAT reclassification request has already been posted.']);
            }
            if ($invoice->status !== 'posted' || ! $invoice->isActiveCorrectionIdentity()) {
                throw ValidationException::withMessages(['invoice' => 'Select the active posted purchase identity as the VAT source.']);
            }
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $period = $this->openPeriod($date);
            $priorAmount = (int) SupplierPurchaseVatReclassification::query()
                ->whereIn('supplier_purchase_invoice_id', $chainIds)->sum('amount_cents');
            $sourceAmount = (int) $invoice->gross_amount_cents;
            if ($data['amount_cents'] > $sourceAmount - $priorAmount) {
                throw ValidationException::withMessages(['amount_cents' => 'The reclassification exceeds the source purchase value remaining after corrections and prior VAT reclassifications.']);
            }
            $lines = $invoice->lines->keyBy('id');
            $sourceCapacity = [];
            foreach ($invoice->lines as $sourceLine) {
                $key = $sourceLine->inventory_id ? 'inventory:'.$sourceLine->inventory_id : 'account:'.$sourceLine->accounting_account_id;
                $sourceCapacity[$key] = ($sourceCapacity[$key] ?? 0) + (int) $sourceLine->line_amount_cents;
            }
            $priorSourceAmounts = DB::table('supplier_purchase_vat_reclassification_lines')
                ->join('supplier_purchase_vat_reclassifications', 'supplier_purchase_vat_reclassifications.id', '=', 'supplier_purchase_vat_reclassification_lines.supplier_purchase_vat_reclassification_id')
                ->whereIn('supplier_purchase_vat_reclassifications.supplier_purchase_invoice_id', $chainIds)
                ->selectRaw('supplier_purchase_vat_reclassification_lines.inventory_id, supplier_purchase_vat_reclassification_lines.accounting_account_id, SUM(supplier_purchase_vat_reclassification_lines.amount_cents) AS amount_cents')
                ->groupBy('supplier_purchase_vat_reclassification_lines.inventory_id', 'supplier_purchase_vat_reclassification_lines.accounting_account_id')
                ->get()->mapWithKeys(fn ($row) => [
                    $row->inventory_id ? 'inventory:'.$row->inventory_id : 'account:'.$row->accounting_account_id => (int) $row->amount_cents,
                ])->all();
            $inputVat = $this->classifiedAccount('input_vat', 'Asset');
            $accounts = [$inputVat->id => $inputVat];
            $journalLines = [$inputVat->id => ['debit_cents' => (int) $data['amount_cents'], 'credit_cents' => 0]];
            $stockChanges = [];
            $reviewed = [];
            $allocated = 0;
            $allocatedBySource = [];
            foreach ($data['allocations'] as $index => $allocation) {
                $line = $lines->get((int) $allocation['line_id']);
                if (! $line) {
                    throw ValidationException::withMessages(["allocations.$index.line_id" => 'Every allocation must belong to the active source invoice.']);
                }
                $amount = (int) $allocation['amount_cents'];
                if ($amount > PHP_INT_MAX - $allocated || $amount > (int) $line->line_amount_cents) {
                    throw ValidationException::withMessages(["allocations.$index.amount_cents" => 'The allocation exceeds its source line or supported centavo range.']);
                }
                $allocated += $amount;
                $remaining = (int) ($allocation['remaining_inventory_cents'] ?? 0);
                $consumed = (int) ($allocation['consumed_cost_cents'] ?? 0);
                if ($line->inventory_id) {
                    abort_unless($actor->can('inventory.valuation.approve'), 403);
                    if ($remaining > PHP_INT_MAX - $consumed || $remaining + $consumed !== $amount) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Reviewed remaining-inventory and consumed-cost allocations must equal the VAT reclassification amount.']);
                    }
                    $sourceKey = 'inventory:'.$line->inventory_id;
                    $allocatedBySource[$sourceKey] = ($allocatedBySource[$sourceKey] ?? 0) + $amount;
                    $inventoryAccount = $this->mappedAccount('inventory');
                    $accounts[$inventoryAccount->id] = $inventoryAccount;
                    if ($remaining > 0) {
                        $this->addJournalAmount($journalLines, $inventoryAccount->id, 'credit_cents', $remaining);
                        $stockChanges[$line->inventory_id] = ($stockChanges[$line->inventory_id] ?? 0) - $remaining;
                    }
                    $consumedAccountId = null;
                    if ($consumed > 0) {
                        $consumedAccount = AccountingAccount::query()->lockForUpdate()->find($allocation['consumed_accounting_account_id'] ?? null);
                        if (! $consumedAccount?->isApprovedForPosting() || $consumedAccount->type !== 'Expense') {
                            throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'Select an active approved expense account for consumed purchase cost.']);
                        }
                        $consumedAccountId = $consumedAccount->id;
                        $accounts[$consumedAccountId] = $consumedAccount;
                        $this->addJournalAmount($journalLines, $consumedAccountId, 'credit_cents', $consumed);
                    } elseif (! empty($allocation['consumed_accounting_account_id'])) {
                        throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'A consumed-cost account is only valid when consumed cost is allocated.']);
                    }
                    $reviewed[] = [
                        'supplier_purchase_line_id' => $line->id, 'inventory_id' => $line->inventory_id,
                        'accounting_account_id' => $inventoryAccount->id, 'consumed_accounting_account_id' => $consumedAccountId,
                        'remaining_inventory_cents' => $remaining, 'consumed_cost_cents' => $consumed, 'amount_cents' => $amount,
                    ];
                } else {
                    if ($remaining !== 0 || $consumed !== 0 || ! empty($allocation['consumed_accounting_account_id'])) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Non-inventory purchases must credit their original purchase account directly.']);
                    }
                    $account = AccountingAccount::query()->lockForUpdate()->find($line->accounting_account_id);
                    if (! $account?->isApprovedForPosting()) {
                        throw ValidationException::withMessages(["allocations.$index" => 'The original purchase account must remain active and approved.']);
                    }
                    $accounts[$account->id] = $account;
                    $sourceKey = 'account:'.$account->id;
                    $allocatedBySource[$sourceKey] = ($allocatedBySource[$sourceKey] ?? 0) + $amount;
                    $this->addJournalAmount($journalLines, $account->id, 'credit_cents', $amount);
                    $reviewed[] = [
                        'supplier_purchase_line_id' => $line->id, 'inventory_id' => null,
                        'accounting_account_id' => $account->id, 'consumed_accounting_account_id' => null,
                        'remaining_inventory_cents' => 0, 'consumed_cost_cents' => 0, 'amount_cents' => $amount,
                    ];
                }
            }
            if ($allocated !== (int) $data['amount_cents']) {
                throw ValidationException::withMessages(['allocations' => 'Explicit source-account allocations must equal the approved input VAT amount exactly.']);
            }
            foreach ($allocatedBySource as $sourceKey => $amount) {
                if ($amount > ($sourceCapacity[$sourceKey] ?? 0) - ($priorSourceAmounts[$sourceKey] ?? 0)) {
                    throw ValidationException::withMessages(['allocations' => 'Reclassifications against an original purchase account cannot exceed its remaining source amount.']);
                }
            }
            foreach ($accounts as $account) {
                if (! $account->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['accounts' => 'Every reclassification account must be active and approved.']);
                }
            }
            $this->assertForwardInventoryDate($stockChanges, $date);
            foreach ($stockChanges as $inventoryId => $change) {
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                if ((int) $item->carrying_value_cents + $change < 0
                    || ((float) $item->qty === 0.0 && (int) $item->carrying_value_cents + $change !== 0)) {
                    throw ValidationException::withMessages(['allocations' => 'The remaining-inventory allocation exceeds the current carrying value.']);
                }
            }
            if (array_sum(array_column($journalLines, 'debit_cents')) !== array_sum(array_column($journalLines, 'credit_cents'))) {
                throw ValidationException::withMessages(['allocations' => 'Input VAT and reviewed purchase-account credits must balance exactly.']);
            }

            $reference = 'PUR-VAT-'.$invoice->id.'-'.Str::upper(Str::random(8));
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => $reference, 'source_type' => 'supplier_purchase_vat_reclassification',
                'source_id' => (string) $invoice->id, 'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Allowable purchase VAT reclassification for '.$invoice->invoice_number.': '.$reason,
                'external_reference' => $invoice->invoice_number, 'correction_of_id' => $invoice->accounting_journal_id,
                'correction_reason' => $reason, 'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            foreach ($journalLines as $accountId => $amounts) {
                if ($amounts['debit_cents'] === 0 && $amounts['credit_cents'] === 0) {
                    continue;
                }
                $journal->lines()->create(['accounting_account_id' => $accountId, ...$amounts]);
                $accounts[$accountId]->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();
            $reclassification = SupplierPurchaseVatReclassification::query()->create([
                'supplier_purchase_invoice_id' => $invoice->id, 'idempotency_key' => $data['idempotency_key'], 'journal_id' => $journal->id,
                'amount_cents' => $data['amount_cents'], 'reason' => $reason, 'accounting_date' => $date->toDateString(),
                'prepared_by' => $actor->id, 'posted_by' => $actor->id,
            ]);
            foreach ($reviewed as $line) {
                $reclassification->lines()->create($line);
            }
            foreach ($stockChanges as $inventoryId => $change) {
                if ($change === 0) {
                    continue;
                }
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                $after = (int) $item->carrying_value_cents + $change;
                StockMovement::query()->create([
                    'inventory_id' => $item->id, 'posted_by' => $actor->id, 'type' => 'adjustment', 'quantity' => '0.00',
                    'reason_category' => 'supplier_purchase_vat_reclassification', 'notes' => $reason,
                    'reference' => $reference, 'effective_date' => $date->toDateString(), 'posted_at' => now('UTC'),
                    'value_cents' => $change, 'carrying_value_after_cents' => $after, 'accounting_journal_id' => $journal->id,
                    'source_reference' => 'supplier_purchase_vat_reclassification:'.$reclassification->id,
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'carrying_value_cents' => $after,
                    'unit_cost' => (float) $item->qty === 0.0 ? '0.00' : number_format($after / ((float) $item->qty * 100), 2, '.', ''),
                ]);
            }

            return $reclassification->refresh()->load(['journal.lines.account', 'lines.purchaseLine', 'lines.account', 'lines.consumedAccount']);
        });
    }


    public function reclassifyDirectPurchase(int $disbursementId, array $input, int $actorId): SupplierPurchaseVatReclassification
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.correct-supplier-purchases'), 403);
        $data = Validator::make($input, [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:4000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.line_id' => ['required', 'integer', 'distinct', 'exists:cash_disbursement_lines,id'],
            'allocations.*.amount_cents' => ['required', 'integer', 'min:1'],
            'allocations.*.remaining_inventory_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_cost_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Supporting rationale is required.']);
        }

        return DB::transaction(function () use ($disbursementId, $data, $reason, $actor): SupplierPurchaseVatReclassification {
            $payment = \App\Models\CashDisbursement::query()->with('lines')->lockForUpdate()->findOrFail($disbursementId);
            if (SupplierPurchaseVatReclassification::query()->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['idempotency_key' => 'This purchase VAT reclassification request has already been posted.']);
            }
            $priorCorrections = \App\Models\SupplierPurchaseCorrection::query()->where('cash_disbursement_id', $payment->id)->orderBy('id')->get();
            if ($payment->status !== 'posted' || $payment->reversal_of_id !== null || $payment->supplier_id !== null
                || $payment->lines->isEmpty() || $payment->lines->contains(fn ($line) => $line->supplier_purchase_invoice_id || $line->supplier_opening_invoice_id)
                || \App\Models\CashDisbursement::query()->where('reversal_of_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['source' => 'Select an unreversed posted direct purchase source.']);
            }
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $period = $this->openPeriod($date);
            $currentLineAmounts = $payment->lines->mapWithKeys(fn ($line) => [$line->id => (int) $line->amount_cents])->all();
            $priorCorrectionsForLines = DB::table('supplier_purchase_correction_lines')
                ->join('supplier_purchase_corrections', 'supplier_purchase_corrections.id', '=', 'supplier_purchase_correction_lines.supplier_purchase_correction_id')
                ->where('supplier_purchase_corrections.cash_disbursement_id', $payment->id)
                ->get(['cash_disbursement_line_id', 'direction', 'amount_cents']);
            foreach ($priorCorrectionsForLines as $prior) {
                $lineId = $prior->cash_disbursement_line_id;
                if (isset($currentLineAmounts[$lineId])) {
                    $currentLineAmounts[$lineId] += $prior->direction === 'increase' ? (int) $prior->amount_cents : -(int) $prior->amount_cents;
                }
            }
            if (collect($currentLineAmounts)->contains(fn ($amount) => $amount < 1)) {
                throw ValidationException::withMessages(['source' => 'Prior source corrections leave an invalid purchase allocation.']);
            }
            $sourceAmount = array_sum($currentLineAmounts);
            $priorVat = (int) SupplierPurchaseVatReclassification::query()->where('cash_disbursement_id', $payment->id)->sum('amount_cents');
            if ($data['amount_cents'] > $sourceAmount - $priorVat) {
                throw ValidationException::withMessages(['amount_cents' => 'The reclassification exceeds the direct purchase value remaining after corrections and prior VAT reclassifications.']);
            }
            $sourceCapacity = [];
            foreach ($payment->lines as $sourceLine) {
                $key = $sourceLine->inventory_id ? 'inventory:'.$sourceLine->inventory_id : 'account:'.$sourceLine->accounting_account_id;
                $sourceCapacity[$key] = ($sourceCapacity[$key] ?? 0) + $currentLineAmounts[$sourceLine->id];
            }
            $priorSourceAmounts = DB::table('supplier_purchase_vat_reclassification_lines')
                ->join('supplier_purchase_vat_reclassifications', 'supplier_purchase_vat_reclassifications.id', '=', 'supplier_purchase_vat_reclassification_lines.supplier_purchase_vat_reclassification_id')
                ->where('supplier_purchase_vat_reclassifications.cash_disbursement_id', $payment->id)
                ->selectRaw('supplier_purchase_vat_reclassification_lines.inventory_id, supplier_purchase_vat_reclassification_lines.accounting_account_id, SUM(supplier_purchase_vat_reclassification_lines.amount_cents) AS amount_cents')
                ->groupBy('supplier_purchase_vat_reclassification_lines.inventory_id', 'supplier_purchase_vat_reclassification_lines.accounting_account_id')
                ->get()->mapWithKeys(fn ($row) => [
                    $row->inventory_id ? 'inventory:'.$row->inventory_id : 'account:'.$row->accounting_account_id => (int) $row->amount_cents,
                ])->all();
            $lineRecords = $payment->lines->keyBy('id');
            $inputVat = $this->classifiedAccount('input_vat', 'Asset');
            $accounts = [$inputVat->id => $inputVat];
            $journalLines = [$inputVat->id => ['debit_cents' => (int) $data['amount_cents'], 'credit_cents' => 0]];
            $stockChanges = [];
            $reviewed = [];
            $allocated = 0;
            $allocatedBySource = [];
            foreach ($data['allocations'] as $index => $allocation) {
                $line = $lineRecords->get((int) $allocation['line_id']);
                if (! $line) {
                    throw ValidationException::withMessages(["allocations.$index.line_id" => 'Every allocation must belong to the selected direct purchase.']);
                }
                $amount = (int) $allocation['amount_cents'];
                if ($amount > PHP_INT_MAX - $allocated || $amount > $currentLineAmounts[$line->id]) {
                    throw ValidationException::withMessages(["allocations.$index.amount_cents" => 'The allocation exceeds its corrected source line or supported centavo range.']);
                }
                $allocated += $amount;
                $remaining = (int) ($allocation['remaining_inventory_cents'] ?? 0);
                $consumed = (int) ($allocation['consumed_cost_cents'] ?? 0);
                $inventoryId = $line->inventory_id ? (int) $line->inventory_id : null;
                if ($inventoryId !== null) {
                    abort_unless($actor->can('inventory.valuation.approve'), 403);
                    if ($remaining > PHP_INT_MAX - $consumed || $remaining + $consumed !== $amount) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Reviewed remaining-inventory and consumed-cost allocations must equal the VAT reclassification amount.']);
                    }
                    $sourceAccount = $this->mappedAccount('inventory');
                    $sourceKey = 'inventory:'.$inventoryId;
                    $allocatedBySource[$sourceKey] = ($allocatedBySource[$sourceKey] ?? 0) + $amount;
                    if ($remaining > 0) {
                        $this->addJournalAmount($journalLines, $sourceAccount->id, 'credit_cents', $remaining);
                        $stockChanges[$inventoryId] = ($stockChanges[$inventoryId] ?? 0) - $remaining;
                    }
                    $consumedAccountId = null;
                    if ($consumed > 0) {
                        $consumedAccount = AccountingAccount::query()->lockForUpdate()->find($allocation['consumed_accounting_account_id'] ?? null);
                        if (! $consumedAccount?->isApprovedForPosting() || $consumedAccount->type !== 'Expense') {
                            throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'Select an active approved expense account for consumed purchase cost.']);
                        }
                        $consumedAccountId = $consumedAccount->id;
                        $accounts[$consumedAccountId] = $consumedAccount;
                        $this->addJournalAmount($journalLines, $consumedAccountId, 'credit_cents', $consumed);
                    } elseif (! empty($allocation['consumed_accounting_account_id'])) {
                        throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'A consumed-cost account is only valid when consumed cost is allocated.']);
                    }
                    $accounts[$sourceAccount->id] = $sourceAccount;
                } else {
                    if ($remaining !== 0 || $consumed !== 0 || ! empty($allocation['consumed_accounting_account_id'])) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Non-inventory purchases must credit their original purchase account directly.']);
                    }
                    $sourceAccount = AccountingAccount::query()->lockForUpdate()->find($line->accounting_account_id);
                    if (! $sourceAccount?->isApprovedForPosting()) {
                        throw ValidationException::withMessages(["allocations.$index" => 'The original purchase account must remain active and approved.']);
                    }
                    $accounts[$sourceAccount->id] = $sourceAccount;
                    $sourceKey = 'account:'.$sourceAccount->id;
                    $allocatedBySource[$sourceKey] = ($allocatedBySource[$sourceKey] ?? 0) + $amount;
                    $this->addJournalAmount($journalLines, $sourceAccount->id, 'credit_cents', $amount);
                    $consumedAccountId = null;
                }
                $reviewed[] = [
                    'cash_disbursement_line_id' => $line->id, 'supplier_purchase_line_id' => null,
                    'inventory_id' => $inventoryId, 'accounting_account_id' => $sourceAccount->id,
                    'consumed_accounting_account_id' => $consumedAccountId, 'remaining_inventory_cents' => $remaining,
                    'consumed_cost_cents' => $consumed, 'amount_cents' => $amount,
                ];
            }
            if ($allocated !== (int) $data['amount_cents']) {
                throw ValidationException::withMessages(['allocations' => 'Explicit source-account allocations must equal the approved input VAT amount exactly.']);
            }
            foreach ($allocatedBySource as $sourceKey => $amount) {
                if ($amount > ($sourceCapacity[$sourceKey] ?? 0) - ($priorSourceAmounts[$sourceKey] ?? 0)) {
                    throw ValidationException::withMessages(['allocations' => 'Reclassifications against an original direct-purchase account cannot exceed its remaining source amount.']);
                }
            }
            foreach ($accounts as $account) {
                if (! $account->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['accounts' => 'Every reclassification account must be active and approved.']);
                }
            }
            $this->assertForwardInventoryDate($stockChanges, $date);
            foreach ($stockChanges as $inventoryId => $change) {
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                if ((int) $item->carrying_value_cents + $change < 0
                    || ((float) $item->qty === 0.0 && (int) $item->carrying_value_cents + $change !== 0)) {
                    throw ValidationException::withMessages(['allocations' => 'The remaining-inventory allocation exceeds current carrying value.']);
                }
            }
            if (array_sum(array_column($journalLines, 'debit_cents')) !== array_sum(array_column($journalLines, 'credit_cents'))) {
                throw ValidationException::withMessages(['allocations' => 'Input VAT and reviewed purchase-account credits must balance exactly.']);
            }
            $reference = 'DIRECT-VAT-'.$payment->id.'-'.Str::upper(Str::random(8));
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => $reference, 'source_type' => 'direct_purchase_vat_reclassification',
                'source_id' => (string) $payment->id, 'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Allowable direct-purchase VAT reclassification for '.$payment->reference.': '.$reason,
                'external_reference' => $payment->reference, 'correction_of_id' => $priorCorrections->last()?->journal_id ?? $payment->journal_id,
                'correction_reason' => $reason, 'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            foreach ($journalLines as $accountId => $amounts) {
                $journal->lines()->create(['accounting_account_id' => $accountId, ...$amounts]);
                $accounts[$accountId]->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();
            $reclassification = SupplierPurchaseVatReclassification::query()->create([
                'cash_disbursement_id' => $payment->id, 'idempotency_key' => $data['idempotency_key'], 'journal_id' => $journal->id,
                'amount_cents' => $data['amount_cents'], 'reason' => $reason, 'accounting_date' => $date->toDateString(),
                'prepared_by' => $actor->id, 'posted_by' => $actor->id,
            ]);
            foreach ($reviewed as $line) {
                $reclassification->lines()->create($line);
            }
            foreach ($stockChanges as $inventoryId => $change) {
                if ($change === 0) {
                    continue;
                }
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                $after = (int) $item->carrying_value_cents + $change;
                StockMovement::query()->create([
                    'inventory_id' => $item->id, 'posted_by' => $actor->id, 'type' => 'adjustment', 'quantity' => '0.00',
                    'reason_category' => 'supplier_purchase_vat_reclassification', 'notes' => $reason, 'reference' => $reference,
                    'effective_date' => $date->toDateString(), 'posted_at' => now('UTC'), 'value_cents' => $change,
                    'carrying_value_after_cents' => $after, 'accounting_journal_id' => $journal->id,
                    'source_reference' => 'supplier_purchase_vat_reclassification:'.$reclassification->id,
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'carrying_value_cents' => $after,
                    'unit_cost' => (float) $item->qty === 0.0 ? '0.00' : number_format($after / ((float) $item->qty * 100), 2, '.', ''),
                ]);
            }

            return $reclassification->refresh()->load(['journal.lines.account', 'lines.account', 'lines.consumedAccount']);
        });
    }
    private function openPeriod(CarbonImmutable $date): AccountingPostingPeriod
    {
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'Purchase VAT reclassifications cannot be future-dated.']);
        }
        $opening = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->first();
        if (! $opening || $date->lt(CarbonImmutable::parse($opening->accounting_date, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['date' => 'Purchase VAT reclassifications require an accounting date on or after approved cutover.']);
        }
        $period = AccountingPostingPeriod::query()->firstOrCreate(
            ['book_key' => 'FCDC', 'fiscal_year' => $date->year],
            ['starts_on' => $date->startOfYear()->toDateString(), 'ends_on' => $date->endOfYear()->toDateString(), 'status' => 'open'],
        );
        $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($period->id);
        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['date' => 'The purchase VAT reclassification date is in a closed accounting period.']);
        }

        return $period;
    }

    private function mappedAccount(string $source): AccountingAccount
    {
        $mapping = \App\Models\AccountingPostingMapping::query()->where('source', $source)->with('account')->lockForUpdate()->first();
        if (! $mapping?->isApprovedForPosting()) {
            throw ValidationException::withMessages(['mapping' => "An active approved {$source} posting mapping is required."]);
        }

        return AccountingAccount::query()->lockForUpdate()->findOrFail($mapping->accounting_account_id);
    }

    private function classifiedAccount(string $classification, string $type): AccountingAccount
    {
        $account = AccountingAccount::query()->where('classification', $classification)->where('type', $type)
            ->where('is_active', true)->whereNotNull('approved_at')->lockForUpdate()->first();
        if (! $account?->isApprovedForPosting()) {
            throw ValidationException::withMessages(['accounting' => "An active approved {$classification} account is required."]);
        }

        return $account;
    }

    private function addJournalAmount(array &$lines, int $accountId, string $side, int $amount): void
    {
        $lines[$accountId] ??= ['debit_cents' => 0, 'credit_cents' => 0];
        if ($amount > PHP_INT_MAX - $lines[$accountId][$side]) {
            throw ValidationException::withMessages(['allocations' => 'The journal total exceeds the supported centavo range.']);
        }
        $lines[$accountId][$side] += $amount;
    }

    private function assertForwardInventoryDate(array $changes, CarbonImmutable $date): void
    {
        foreach (array_keys($changes) as $inventoryId) {
            $latest = StockMovement::query()->where('inventory_id', $inventoryId)->whereNotNull('value_cents')
                ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
            if ($latest && $date->toDateString() < $latest->effective_date->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Purchase VAT reclassification cannot precede an inventory item’s latest valued movement.']);
            }
        }
    }
}
