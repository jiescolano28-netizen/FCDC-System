<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\SupplierPurchaseCorrection;
use App\Models\SupplierPurchaseInvoice;
use App\Models\SupplierRefundReceipt;
use App\Models\CashDisbursement;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplierPurchaseCorrectionService
{
    public function correct(int $invoiceId, array $input, int $actorId): SupplierPurchaseCorrection
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.correct-supplier-purchases'), 403);
        $data = Validator::make($input, [
            'reason' => ['required', 'string', 'max:4000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.line_id' => ['required', 'integer', 'distinct', 'exists:supplier_purchase_lines,id'],
            'allocations.*.corrected_amount_cents' => ['required', 'integer', 'min:1'],
            'allocations.*.remaining_inventory_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_cost_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
        ])->validate();
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A correction reason is required.']);
        }

        return DB::transaction(function () use ($invoiceId, $data, $reason, $actor): SupplierPurchaseCorrection {
            $invoiceCandidate = SupplierPurchaseInvoice::query()->findOrFail($invoiceId);
            $rootInvoiceId = $invoiceCandidate->correctionChainInvoiceIds()[0];
            SupplierPurchaseInvoice::query()->lockForUpdate()->findOrFail($rootInvoiceId);
            $invoice = SupplierPurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'posted' || $invoice->correctionChildren()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Only the active posted credit purchase identity can be corrected.']);
            }
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $period = $this->openPeriod($date);
            $currentAmount = (int) $invoice->gross_amount_cents;
            $linesById = $invoice->lines->keyBy('id');
            $correctedLines = [];
            $reviewedAdjustments = [];
            $correctedTotal = 0;
            $inventoryChanges = [];
            foreach ($data['allocations'] as $index => $allocation) {
                $line = $linesById->get((int) $allocation['line_id']);
                if (! $line) {
                    throw ValidationException::withMessages(["allocations.$index.line_id" => 'The allocation must belong to the selected posted invoice.']);
                }
                $correctedCents = (int) $allocation['corrected_amount_cents'];
                if ($correctedCents > PHP_INT_MAX - $correctedTotal) {
                    throw ValidationException::withMessages(['allocations' => 'The corrected purchase total exceeds the supported centavo range.']);
                }
                $correctedTotal += $correctedCents;
                $difference = $correctedCents - (int) $line->line_amount_cents;
                if ($line->inventory_id && ! $actor->can('inventory.valuation.approve')) {
                    abort(403);
                }
                $reviewed = [
                    'supplier_purchase_line_id' => $line->id,
                    'inventory_id' => $line->inventory_id,
                    'accounting_account_id' => $line->inventory_id ? $this->mappedAccount('inventory')->id : $line->accounting_account_id,
                    'direction' => $difference < 0 ? 'decrease' : 'increase',
                    'amount_cents' => abs($difference),
                    'remaining_inventory_cents' => 0,
                    'consumed_cost_cents' => 0,
                    'consumed_accounting_account_id' => null,
                ];
                if ($line->inventory_id) {
                    $remaining = (int) ($allocation['remaining_inventory_cents'] ?? 0);
                    $consumed = (int) ($allocation['consumed_cost_cents'] ?? 0);
                    if ($remaining > PHP_INT_MAX - $consumed || $remaining + $consumed !== abs($difference)) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Reviewed remaining-inventory and consumed-cost amounts must equal the inventory line correction.']);
                    }
                    if ($consumed > 0) {
                        $consumedAccount = AccountingAccount::query()->lockForUpdate()->find($allocation['consumed_accounting_account_id'] ?? null);
                        if (! $consumedAccount?->isApprovedForPosting() || $consumedAccount->type !== 'Expense') {
                            throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'Select an active approved expense account for consumed stock cost.']);
                        }
                        $reviewed['consumed_accounting_account_id'] = $consumedAccount->id;
                    }
                    $reviewed['remaining_inventory_cents'] = $remaining;
                    $reviewed['consumed_cost_cents'] = $consumed;
                    $inventoryChanges[$line->inventory_id] = ($inventoryChanges[$line->inventory_id] ?? 0)
                        + ($difference < 0 ? -$remaining : $remaining);
                } elseif (! empty($allocation['remaining_inventory_cents']) || ! empty($allocation['consumed_cost_cents'])) {
                    throw ValidationException::withMessages(["allocations.$index" => 'Non-inventory lines cannot have stock cost allocations.']);
                }
                $reviewedAdjustments[] = $reviewed;
                $correctedLines[] = [
                    'inventory_id' => $line->inventory_id,
                    'accounting_account_id' => $line->accounting_account_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'line_amount_cents' => $correctedCents,
                ];
            }
            if ($linesById->count() !== count($correctedLines) || $correctedTotal < 1) {
                throw ValidationException::withMessages(['allocations' => 'Correct every original purchase allocation exactly once and provide a positive corrected total.']);
            }
            $difference = $correctedTotal - $currentAmount;
            if ($difference === 0) {
                throw ValidationException::withMessages(['allocations' => 'The correction must change the posted purchase value.']);
            }
            foreach ($reviewedAdjustments as $adjustment) {
                if ($adjustment['amount_cents'] > 0 && ($adjustment['direction'] === 'increase') !== ($difference > 0)) {
                    throw ValidationException::withMessages(['allocations' => 'A correction cannot increase some purchase lines while decreasing others in one posting.']);
                }
            }
            $paid = $invoice->paidAmountCents();
            $received = $invoice->refundReceivedAmountCents();
            $newRefundDue = max(0, $paid - $received - $correctedTotal);
            $existingRefundDue = $invoice->supplierRefundDueCents();
            $refundDelta = $newRefundDue - $existingRefundDue;
            $currentApDue = max(0, $currentAmount - $paid + $received);
            $newApDue = max(0, $correctedTotal - $paid + $received);
            $apDelta = $newApDue - $currentApDue;

            $accounts = [];
            foreach ($reviewedAdjustments as $index => $adjustment) {
                if ($adjustment['amount_cents'] === 0) {
                    continue;
                }
                if ($adjustment['inventory_id'] !== null && $adjustment['remaining_inventory_cents'] > 0) {
                    $accounts[$adjustment['accounting_account_id']] = AccountingAccount::query()->lockForUpdate()->findOrFail($adjustment['accounting_account_id']);
                } elseif ($adjustment['inventory_id'] === null) {
                    $accounts[$adjustment['accounting_account_id']] = AccountingAccount::query()->lockForUpdate()->findOrFail($adjustment['accounting_account_id']);
                }
                if ($adjustment['consumed_accounting_account_id']) {
                    $accounts[$adjustment['consumed_accounting_account_id']] = AccountingAccount::query()->lockForUpdate()->findOrFail($adjustment['consumed_accounting_account_id']);
                }
            }
            foreach ($accounts as $account) {
                if (! $account->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['allocations' => 'Every correction allocation requires an active approved account.']);
                }
            }
            $ap = $this->mappedAccount('accounts_payable');
            $receivable = $refundDelta !== 0 ? $this->classifiedAccount('accounts_receivable', 'Asset') : null;
            $journalLines = [];
            foreach ($reviewedAdjustments as $adjustment) {
                if ($adjustment['amount_cents'] === 0) {
                    continue;
                }
                $isIncrease = $adjustment['direction'] === 'increase';
                foreach ([
                    [$adjustment['accounting_account_id'], $adjustment['remaining_inventory_cents']],
                    [$adjustment['consumed_accounting_account_id'], $adjustment['consumed_cost_cents']],
                ] as [$accountId, $amount]) {
                    if (! $accountId || $amount < 1) {
                        continue;
                    }
                    $journalLines[$accountId] ??= ['debit_cents' => 0, 'credit_cents' => 0];
                    $journalLines[$accountId][$isIncrease ? 'debit_cents' : 'credit_cents'] += $amount;
                }
                if ($adjustment['inventory_id'] === null) {
                    $accountId = $adjustment['accounting_account_id'];
                    $journalLines[$accountId] ??= ['debit_cents' => 0, 'credit_cents' => 0];
                    $journalLines[$accountId][$isIncrease ? 'debit_cents' : 'credit_cents'] += $adjustment['amount_cents'];
                }
            }
            if ($apDelta !== 0) {
                $journalLines[$ap->id] = ['debit_cents' => max(0, -$apDelta), 'credit_cents' => max(0, $apDelta)];
            }
            if ($refundDelta !== 0) {
                $journalLines[$receivable->id] = ['debit_cents' => max(0, $refundDelta), 'credit_cents' => max(0, -$refundDelta)];
            }
            $totalDebits = array_sum(array_column($journalLines, 'debit_cents'));
            $totalCredits = array_sum(array_column($journalLines, 'credit_cents'));
            if ($totalDebits < 1 || $totalDebits !== $totalCredits) {
                throw ValidationException::withMessages(['allocations' => 'Reviewed correction allocations must balance the controlled payable and purchase accounts exactly.']);
            }
            $this->assertForwardInventoryDate($inventoryChanges, $date);

            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'PUR-CORR-'.$invoice->id.'-'.Str::upper(Str::random(8)),
                'source_type' => 'supplier_purchase_correction', 'source_id' => (string) $invoice->id,
                'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Purchase correction for '.$invoice->invoice_number.': '.$reason,
                'external_reference' => $invoice->invoice_number, 'correction_of_id' => $invoice->accounting_journal_id,
                'correction_reason' => $reason, 'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            foreach ($journalLines as $accountId => $amounts) {
                if ($amounts['debit_cents'] === 0 && $amounts['credit_cents'] === 0) {
                    continue;
                }
                $journal->lines()->create(['accounting_account_id' => $accountId, ...$amounts]);
                AccountingAccount::query()->whereKey($accountId)->firstOrFail()->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();

            $correction = SupplierPurchaseCorrection::query()->create([
                'supplier_purchase_invoice_id' => $invoice->id, 'replacement_invoice_id' => null,
                'journal_id' => $journal->id, 'supplier_id' => $invoice->supplier_id,
                'source_type' => 'supplier_purchase', 'original_amount_cents' => $currentAmount,
                'corrected_amount_cents' => $correctedTotal, 'refund_due_cents' => $refundDelta,
                'reason' => $reason, 'accounting_date' => $date->toDateString(),
                'prepared_by' => $actor->id, 'posted_by' => $actor->id,
            ]);
            foreach ($reviewedAdjustments as $adjustment) {
                $adjustment['supplier_purchase_correction_id'] = $correction->id;
                DB::table('supplier_purchase_correction_lines')->insert($adjustment + [
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($inventoryChanges as $inventoryId => $change) {
                if ($change === 0) {
                    continue;
                }
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                $before = (int) $item->carrying_value_cents;
                $after = $before + $change;
                if ($after < 0 || ((float) $item->qty === 0.0 && $after !== 0)) {
                    throw ValidationException::withMessages(['allocations' => 'The reviewed remaining-stock adjustment exceeds current carrying value.']);
                }
                StockMovement::query()->create([
                    'inventory_id' => $item->id, 'posted_by' => $actor->id, 'type' => 'adjustment',
                    'quantity' => '0.00', 'reason_category' => 'supplier_purchase_correction',
                    'notes' => $reason, 'reference' => 'PUR-CORR-'.$correction->id,
                    'effective_date' => $date->toDateString(), 'posted_at' => now('UTC'),
                    'value_cents' => $change, 'carrying_value_after_cents' => $after,
                    'accounting_journal_id' => $journal->id, 'source_reference' => 'supplier_purchase_correction:'.$correction->id,
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'carrying_value_cents' => $after,
                    'unit_cost' => (float) $item->qty === 0.0 ? '0.00' : number_format($after / ((float) $item->qty * 100), 2, '.', ''),
                ]);
            }
            $replacementSuffix = '-C'.$correction->id;
            $replacementNumber = mb_substr($invoice->invoice_number, 0, 100 - mb_strlen($replacementSuffix, 'UTF-8'), 'UTF-8').$replacementSuffix;
            $sequence = 1;
            while (SupplierPurchaseInvoice::query()
                ->where('supplier_id', $invoice->supplier_id)
                ->where('invoice_number_normalized', mb_strtoupper($replacementNumber, 'UTF-8'))
                ->exists()) {
                $sequence++;
                $replacementSuffix = '-C'.$correction->id.'-'.$sequence;
                $replacementNumber = mb_substr($invoice->invoice_number, 0, 100 - mb_strlen($replacementSuffix, 'UTF-8'), 'UTF-8').$replacementSuffix;
            }
            $replacement = SupplierPurchaseInvoice::query()->create([
                'supplier_id' => $invoice->supplier_id, 'supplier_code_snapshot' => $invoice->supplier_code_snapshot,
                'supplier_name_snapshot' => $invoice->supplier_name_snapshot, 'invoice_number' => $replacementNumber,
                'invoice_number_normalized' => mb_strtoupper($replacementNumber, 'UTF-8'),
                'recognition_date' => $invoice->recognition_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'gross_amount_cents' => $correctedTotal, 'description' => 'Corrected purchase: '.$invoice->description,
                'terms' => $invoice->terms, 'receipt_confirmed' => true, 'status' => 'draft',
                'prepared_by' => $actor->id,
                'accounting_journal_id' => $journal->id, 'correction_of_id' => $invoice->id, 'correction_reason' => $reason,
            ]);
            foreach ($correctedLines as $correctedLine) {
                $replacement->lines()->create($correctedLine);
            }
            $replacement->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
            ])->save();
            $correction->forceFill(['replacement_invoice_id' => $replacement->id])->save();

            return $correction->refresh()->load(['journal.lines.account', 'invoice', 'refundReceipts']);
        });
    }

    public function correctDirectPurchase(int $disbursementId, array $input, int $actorId): SupplierPurchaseCorrection
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.correct-supplier-purchases'), 403);
        $data = Validator::make($input, [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'reason' => ['required', 'string', 'max:4000'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.line_id' => ['required', 'integer', 'distinct', 'exists:cash_disbursement_lines,id'],
            'allocations.*.corrected_amount_cents' => ['required', 'integer', 'min:1'],
            'allocations.*.remaining_inventory_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_cost_cents' => ['nullable', 'integer', 'min:0'],
            'allocations.*.consumed_accounting_account_id' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
        ])->validate();
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A correction reason is required.']);
        }

        return DB::transaction(function () use ($disbursementId, $data, $reason, $actor): SupplierPurchaseCorrection {
            $payment = CashDisbursement::query()->with(['lines', 'journal'])->lockForUpdate()->findOrFail($disbursementId);
            $previousCorrections = SupplierPurchaseCorrection::query()->where('cash_disbursement_id', $payment->id)->orderBy('id')->get();
            if ($payment->status !== 'posted' || $payment->reversal_of_id !== null || $payment->supplier_id !== null
                || $payment->lines->isEmpty() || $payment->lines->contains(fn ($line) => $line->supplier_purchase_invoice_id || $line->supplier_opening_invoice_id)
                || CashDisbursement::query()->where('reversal_of_id', $payment->id)->exists()) {
                throw ValidationException::withMessages(['disbursement' => 'Only an unreversed posted direct purchase can be corrected.']);
            }
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $date = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $period = $this->openPeriod($date);
            $linesById = $payment->lines->keyBy('id');
            $originalAmount = (int) $payment->lines->sum('amount_cents');
            $currentAmount = (int) ($previousCorrections->last()?->corrected_amount_cents ?? $originalAmount);
            $currentLineAmounts = $payment->lines->mapWithKeys(fn ($line) => [$line->id => (int) $line->amount_cents])->all();
            $previousAllocations = DB::table('supplier_purchase_correction_lines')
                ->join('supplier_purchase_corrections', 'supplier_purchase_corrections.id', '=', 'supplier_purchase_correction_lines.supplier_purchase_correction_id')
                ->where('supplier_purchase_corrections.cash_disbursement_id', $payment->id)
                ->get(['cash_disbursement_line_id', 'direction', 'amount_cents']);
            foreach ($previousAllocations as $previousAllocation) {
                $currentLineAmounts[$previousAllocation->cash_disbursement_line_id] += $previousAllocation->direction === 'increase'
                    ? (int) $previousAllocation->amount_cents
                    : -(int) $previousAllocation->amount_cents;
            }
            if (collect($currentLineAmounts)->contains(fn ($amount) => $amount < 1)) {
                throw ValidationException::withMessages(['disbursement' => 'Prior direct purchase corrections leave an invalid line value.']);
            }
            $correctedTotal = 0;
            $reviewed = [];
            $inventoryChanges = [];
            $accounts = [];
            foreach ($data['allocations'] as $index => $allocation) {
                $line = $linesById->get((int) $allocation['line_id']);
                if (! $line) {
                    throw ValidationException::withMessages(["allocations.$index.line_id" => 'Select an allocation from this direct purchase.']);
                }
                $correctedCents = (int) $allocation['corrected_amount_cents'];
                if ($correctedCents > PHP_INT_MAX - $correctedTotal) {
                    throw ValidationException::withMessages(['allocations' => 'The corrected direct purchase total exceeds the supported centavo range.']);
                }
                $lineDifference = $correctedCents - $currentLineAmounts[$line->id];
                $amount = abs($lineDifference);
                $inventoryId = $line->inventory_id ? (int) $line->inventory_id : null;
                $accountId = $inventoryId ? $this->mappedAccount('inventory')->id : (int) $line->accounting_account_id;
                $remaining = (int) ($allocation['remaining_inventory_cents'] ?? 0);
                $consumed = (int) ($allocation['consumed_cost_cents'] ?? 0);
                if ($inventoryId !== null) {
                    abort_unless($actor->can('inventory.valuation.approve'), 403);
                    if ($remaining > PHP_INT_MAX - $consumed || $remaining + $consumed !== $amount) {
                        throw ValidationException::withMessages(["allocations.$index" => 'Reviewed remaining-inventory and consumed-cost amounts must equal the direct purchase correction.']);
                    }
                    if ($consumed > 0) {
                        $consumedAccount = AccountingAccount::query()->lockForUpdate()->find($allocation['consumed_accounting_account_id'] ?? null);
                        if (! $consumedAccount?->isApprovedForPosting() || $consumedAccount->type !== 'Expense') {
                            throw ValidationException::withMessages(["allocations.$index.consumed_accounting_account_id" => 'Select an active approved expense account for consumed stock cost.']);
                        }
                        $accounts[$consumedAccount->id] = $consumedAccount;
                    }
                    $inventoryChanges[$inventoryId] = ($inventoryChanges[$inventoryId] ?? 0)
                        + ($lineDifference < 0 ? -$remaining : $remaining);
                } elseif ($remaining !== 0 || $consumed !== 0 || ! empty($allocation['consumed_accounting_account_id'])) {
                    throw ValidationException::withMessages(["allocations.$index" => 'Non-inventory direct purchases cannot carry stock cost allocations.']);
                }
                if ($inventoryId === null) {
                    $account = AccountingAccount::query()->lockForUpdate()->find($accountId);
                    if (! $account?->isApprovedForPosting()) {
                        throw ValidationException::withMessages(["allocations.$index.line_id" => 'The original purchase account is no longer active and approved.']);
                    }
                    $accounts[$accountId] = $account;
                }
                $reviewed[] = [
                    'cash_disbursement_line_id' => $line->id, 'supplier_purchase_line_id' => null,
                    'inventory_id' => $inventoryId, 'accounting_account_id' => $accountId,
                    'consumed_accounting_account_id' => ($allocation['consumed_accounting_account_id'] ?? null) ?: null,
                    'direction' => $lineDifference < 0 ? 'decrease' : 'increase', 'amount_cents' => $amount,
                    'remaining_inventory_cents' => $remaining, 'consumed_cost_cents' => $consumed,
                ];
                $correctedTotal += $correctedCents;
            }
            $difference = $correctedTotal - $currentAmount;
            if (count($reviewed) !== $linesById->count() || $correctedTotal < 1 || $difference >= 0) {
                throw ValidationException::withMessages(['allocations' => 'Correct every direct purchase allocation and provide a lower positive total.']);
            }
            foreach ($reviewed as $allocation) {
                if ($allocation['amount_cents'] > 0 && $allocation['direction'] !== 'decrease') {
                    throw ValidationException::withMessages(['allocations' => 'A direct-purchase correction must reduce every changed line.']);
                }
            }
            $this->assertForwardInventoryDate($inventoryChanges, $date);
            $receivable = $this->classifiedAccount('accounts_receivable', 'Asset');
            $journalLines = [$receivable->id => ['debit_cents' => abs($difference), 'credit_cents' => 0]];
            foreach ($reviewed as $allocation) {
                foreach ([
                    [$allocation['accounting_account_id'], $allocation['inventory_id'] ? $allocation['remaining_inventory_cents'] : $allocation['amount_cents']],
                    [$allocation['consumed_accounting_account_id'], $allocation['consumed_cost_cents']],
                ] as [$accountId, $amount]) {
                    if (! $accountId || $amount < 1) {
                        continue;
                    }
                    $journalLines[$accountId] ??= ['debit_cents' => 0, 'credit_cents' => 0];
                    $journalLines[$accountId][$allocation['direction'] === 'increase' ? 'debit_cents' : 'credit_cents'] += $amount;
                }
            }
            $debits = array_sum(array_column($journalLines, 'debit_cents'));
            $credits = array_sum(array_column($journalLines, 'credit_cents'));
            if ($debits !== $credits || $debits !== abs($difference)) {
                throw ValidationException::withMessages(['allocations' => 'Direct purchase correction allocations must balance the supplier refund/payable exactly.']);
            }
            $journalReference = 'DIRECT-CORR-'.$payment->id.'-'.Str::upper(Str::random(8));
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => $journalReference,
                'source_type' => 'direct_purchase_correction', 'source_id' => $journalReference,
                'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Direct purchase correction for '.$payment->reference.': '.$reason,
                'external_reference' => $payment->reference, 'correction_of_id' => $previousCorrections->last()?->journal_id ?? $payment->journal_id,
                'correction_reason' => $reason, 'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            foreach ($journalLines as $accountId => $amounts) {
                $journal->lines()->create(['accounting_account_id' => $accountId, ...$amounts]);
                AccountingAccount::query()->whereKey($accountId)->firstOrFail()->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();
            $correction = SupplierPurchaseCorrection::query()->create([
                'cash_disbursement_id' => $payment->id, 'journal_id' => $journal->id,
                'supplier_id' => $supplier->id, 'source_type' => 'direct_purchase',
                'original_amount_cents' => $currentAmount, 'corrected_amount_cents' => $correctedTotal,
                'refund_due_cents' => max(0, -$difference), 'reason' => $reason,
                'accounting_date' => $date->toDateString(), 'prepared_by' => $actor->id, 'posted_by' => $actor->id,
            ]);
            foreach ($reviewed as $allocation) {
                $allocation['supplier_purchase_correction_id'] = $correction->id;
                DB::table('supplier_purchase_correction_lines')->insert($allocation + ['created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($inventoryChanges as $inventoryId => $change) {
                if ($change === 0) {
                    continue;
                }
                $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
                $after = (int) $item->carrying_value_cents + $change;
                if ($after < 0 || ((float) $item->qty === 0.0 && $after !== 0)) {
                    throw ValidationException::withMessages(['allocations' => 'The direct-purchase correction exceeds current inventory carrying value.']);
                }
                StockMovement::query()->create([
                    'inventory_id' => $item->id, 'posted_by' => $actor->id, 'type' => 'adjustment',
                    'quantity' => '0.00', 'reason_category' => 'supplier_purchase_correction',
                    'notes' => $reason, 'reference' => 'DIRECT-CORR-'.$correction->id,
                    'effective_date' => $date->toDateString(), 'posted_at' => now('UTC'),
                    'value_cents' => $change, 'carrying_value_after_cents' => $after,
                    'accounting_journal_id' => $journal->id, 'source_reference' => 'supplier_purchase_correction:'.$correction->id,
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'carrying_value_cents' => $after,
                    'unit_cost' => (float) $item->qty === 0.0 ? '0.00' : number_format($after / ((float) $item->qty * 100), 2, '.', ''),
                ]);
            }

            return $correction->refresh()->load(['journal.lines.account', 'refundReceipts']);
        });
    }

    public function receiveRefund(int $correctionId, array $input, int $actorId): SupplierRefundReceipt
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.post-supplier-refunds'), 403);
        $data = Validator::make($input, [
            'amount_cents' => ['required', 'integer', 'min:1'],
            'money_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'reference' => ['required', 'string', 'max:100'],
            'evidence_reference' => ['required', 'string', 'max:255'],
            'receipt_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return DB::transaction(function () use ($correctionId, $data, $actor): SupplierRefundReceipt {
            $correctionCandidate = SupplierPurchaseCorrection::query()->with('invoice')->findOrFail($correctionId);
            $rootInvoice = null;
            if ($correctionCandidate->invoice) {
                $rootInvoiceId = $correctionCandidate->invoice->correctionChainInvoiceIds()[0];
                $rootInvoice = SupplierPurchaseInvoice::query()->lockForUpdate()->findOrFail($rootInvoiceId);
            }
            $correction = SupplierPurchaseCorrection::query()->lockForUpdate()->findOrFail($correctionId);
            $due = $rootInvoice
                ? $rootInvoice->supplierRefundDueCents()
                : (int) $correction->refund_due_cents - (int) $correction->refundReceipts()->sum('amount_cents');
            $amount = (int) $data['amount_cents'];
            if ($due < 1 || $amount > $due) {
                throw ValidationException::withMessages(['amount_cents' => 'The refund receipt cannot exceed the outstanding linked supplier refund.']);
            }
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['receipt_date'], 'Asia/Manila');
            if ($date->isFuture()) {
                throw ValidationException::withMessages(['receipt_date' => 'Refund receipts cannot be future-dated.']);
            }
            $period = $this->openPeriod($date);
            $money = AccountingAccount::query()->lockForUpdate()->findOrFail($data['money_account_id']);
            if (! $money->isApprovedForPosting() || $money->type !== 'Asset' || ! in_array($money->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['money_account_id' => 'Select an active approved Cash or Bank account.']);
            }
            $receivable = $this->classifiedAccount('accounts_receivable', 'Asset');
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'SUP-REFUND-'.$correction->id.'-'.Str::upper(Str::random(8)),
                'source_type' => 'supplier_refund_receipt', 'source_id' => (string) Str::uuid(),
                'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Supplier refund receipt for correction '.$correction->id,
                'external_reference' => trim($data['reference']), 'correction_of_id' => $correction->journal_id,
                'correction_reason' => 'Actual supplier refund received: '.trim($data['evidence_reference']),
                'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            $journal->lines()->createMany([
                ['accounting_account_id' => $money->id, 'debit_cents' => $amount, 'credit_cents' => 0],
                ['accounting_account_id' => $receivable->id, 'debit_cents' => 0, 'credit_cents' => $amount],
            ]);
            $journal->forceFill([
                'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now('UTC'),
                'approved_by' => $actor->id, 'approved_at' => now('UTC'),
            ])->save();
            $money->markUsed();
            $receivable->markUsed();

            return SupplierRefundReceipt::query()->create([
                'supplier_purchase_correction_id' => $correction->id, 'money_account_id' => $money->id,
                'journal_id' => $journal->id, 'reference' => trim($data['reference']),
                'evidence_reference' => trim($data['evidence_reference']), 'amount_cents' => $amount,
                'receipt_date' => $date->toDateString(), 'posted_by' => $actor->id,
            ]);
        });
    }

    private function openPeriod(CarbonImmutable $date): AccountingPostingPeriod
    {
        if ($date->isFuture()) {
            throw ValidationException::withMessages(['date' => 'Supplier corrections and refunds cannot be future-dated.']);
        }
        $opening = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->first();
        if (! $opening || $date->lt(CarbonImmutable::parse($opening->accounting_date, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['date' => 'Supplier corrections and refunds require an accounting date on or after approved cutover.']);
        }
        $period = AccountingPostingPeriod::query()->firstOrCreate(
            ['book_key' => 'FCDC', 'fiscal_year' => $date->year],
            ['starts_on' => $date->startOfYear()->toDateString(), 'ends_on' => $date->endOfYear()->toDateString(), 'status' => 'open'],
        );
        $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($period->id);
        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['date' => 'The supplier correction/refund date is in a closed accounting period.']);
        }

        return $period;
    }

    private function mappedAccount(string $source): AccountingAccount
    {
        $mapping = AccountingPostingMapping::query()->where('source', $source)->with('account')->lockForUpdate()->first();
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

    private function assertForwardInventoryDate(array $changes, CarbonImmutable $date): void
    {
        foreach (array_keys($changes) as $inventoryId) {
            $latest = StockMovement::query()->where('inventory_id', $inventoryId)->whereNotNull('value_cents')
                ->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
            if ($latest && $date->toDateString() < $latest->effective_date->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Purchase cost corrections cannot precede an inventory item’s latest valued movement.']);
            }
        }
    }
}
