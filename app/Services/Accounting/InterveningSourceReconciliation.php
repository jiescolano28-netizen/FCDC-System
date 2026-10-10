<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\CashDisbursement;
use App\Models\Employee;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Models\SupplierPurchaseInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterveningSourceReconciliation
{
    /** @return Collection<int, array{type:string,id:int,date:string,reference:string,description:string,status:string,reason:?string,journal_id:?int}> */
    public function sources(): Collection
    {
        $opening = $this->opening();
        if (! $opening) {
            return collect();
        }
        $cutover = $opening->accounting_date->toDateString();
        $sources = collect();

        $cutoverInstant = CarbonImmutable::parse($cutover, 'Asia/Manila')->startOfDay()->utc();
        PosTransaction::query()->where('status', 'completed')->where('completed_at', '>=', $cutoverInstant)
            ->orderBy('completed_at')->orderBy('id')->with('lines', 'vatRecord')->get()
            ->each(fn (PosTransaction $sale) => $sources->push($this->row('pos_sale', $sale->id,
                CarbonImmutable::parse($sale->completed_at)->timezone('Asia/Manila')->toDateString(),
                $sale->transaction_number, 'POS sale', $this->posEvidenceMatches($sale, false))));

        SupplierPurchaseInvoice::query()->where('status', 'posted')->whereDate('recognition_date', '>=', $cutover)
            ->orderBy('recognition_date')->orderBy('id')->with('lines')->get()->each(fn (SupplierPurchaseInvoice $invoice) => $sources->push(
                $this->row('supplier_purchase', $invoice->id, $invoice->recognition_date->toDateString(), $invoice->invoice_number,
                    'Supplier purchase', $this->purchaseEvidenceMatches($invoice))));

        CashDisbursement::query()->where('status', 'posted')->whereNull('reversal_of_id')->whereDate('payment_date', '>=', $cutover)
            ->orderBy('payment_date')->orderBy('id')->with('lines')->get()->each(fn (CashDisbursement $payment) => $sources->push(
                $this->row('cash_disbursement', $payment->id, $payment->payment_date->toDateString(), $payment->reference, 'Payment',
                    $this->paymentEvidenceMatches($payment, false))));

        CashDisbursement::query()->where('status', 'posted')->whereNotNull('reversal_of_id')->whereDate('payment_date', '>=', $cutover)
            ->orderBy('payment_date')->orderBy('id')->get()->each(fn (CashDisbursement $reversal) => $sources->push(
                $this->row('cash_disbursement_reversal', (int) $reversal->reversal_of_id, $reversal->payment_date->toDateString(),
                    $reversal->reference, 'Payment reversal', false)));

        StockMovement::query()->where('type', '!=', 'opening_balance')->whereNull('cash_disbursement_line_id')
            ->whereDate('effective_date', '>=', $cutover)
            ->where(fn ($query) => $query->whereNull('source_reference')->orWhere(fn ($query) => $query
                ->where('source_reference', 'not like', 'supplier_purchase:%')
                ->where('source_reference', 'not like', 'pos_sale:%')))
            ->orderBy('effective_date')->orderBy('id')->get()->each(fn (StockMovement $movement) => $sources->push(
                $this->row('stock_movement', $movement->id, $movement->effective_date->toDateString(), $movement->reference ?: 'Stock movement '.$movement->id, 'Stock movement', $movement->value_cents !== null && $movement->carrying_value_after_cents !== null && (int) $movement->value_cents !== 0)));

        return $sources->sortBy([['date', 'asc'], ['id', 'asc']])->values();
    }

    public function includePosSale(int $saleId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($saleId, $actorId): AccountingJournal {
            $this->authorizeSourceReconciliation($actorId);
            $sale = PosTransaction::query()->with('lines', 'vatRecord')->lockForUpdate()->findOrFail($saleId);
            if ($sale->status !== 'completed' || ! $sale->vatRecord || $sale->lines->isEmpty()) {
                throw ValidationException::withMessages(['source' => 'A completed sale with its saved VAT record and item lines is required.']);
            }
            if (AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->exists()) {
                throw ValidationException::withMessages(['source' => 'This source already has an accounting link.']);
            }
            $date = CarbonImmutable::parse($sale->completed_at)->timezone('Asia/Manila');
            $opening = $this->opening();
            if (! $opening || $date->lt($opening->accounting_date) || $date->isFuture()) {
                throw ValidationException::withMessages(['source' => 'The sale date is outside approved cutover coverage or is future-dated.']);
            }
            $period = $this->openPeriod($date->toDateString());
            $subtotal = $this->cents((string) $sale->subtotal);
            $vat = $this->cents((string) $sale->vat_amount);
            $total = $this->cents((string) $sale->total);
            if ($subtotal <= 0 || $vat < 0 || $total !== $subtotal + $vat
                || $this->cents((string) $sale->vatRecord->taxable_sales) !== $subtotal
                || $this->cents((string) $sale->vatRecord->output_vat) !== $vat
                || $this->cents((string) $sale->vatRecord->total) !== $total) {
                throw ValidationException::withMessages(['source' => 'The saved POS and VAT amounts do not reconcile.']);
            }
            $lineCosts = $sale->lines->map(fn ($line) => $line->line_cost_cents);
            if ($lineCosts->contains(null) || $lineCosts->contains(fn ($cost) => (int) $cost <= 0)) {
                throw ValidationException::withMessages(['source' => 'Frozen historical line cost evidence is missing; current item cost cannot substitute.']);
            }
            $cogs = (int) $lineCosts->sum();
            if ($cogs <= 0) {
                throw ValidationException::withMessages(['source' => 'Saved line costs do not support a positive sale cost.']);
            }
            $stockMovements = StockMovement::query()->where('source_reference', 'pos_sale:'.$sale->id)->get();
            if ($stockMovements->contains(fn (StockMovement $movement) => $movement->value_cents === null
                || $movement->carrying_value_after_cents === null || $movement->accounting_journal_id !== null)) {
                throw ValidationException::withMessages(['source' => 'The sale stock history lacks a reconciled valued issue or already points to another journal.']);
            }
            foreach ($sale->lines->groupBy('inventory_id') as $inventoryId => $lines) {
                $itemMovements = $stockMovements->where('inventory_id', (int) $inventoryId);
                $lineCost = (int) $lines->sum('line_cost_cents');
                $movementCost = abs((int) $itemMovements->sum('value_cents'));
                $lineQuantity = (int) round($lines->sum(fn ($line) => (float) $line->quantity) * 100);
                $movementQuantity = abs((int) round($itemMovements->sum(fn (StockMovement $movement) => (float) $movement->quantity) * 100));
                if ($itemMovements->isEmpty() || $lineCost !== $movementCost || $lineQuantity !== $movementQuantity) {
                    throw ValidationException::withMessages(['source' => 'Saved sale costs and quantities do not reconcile to the existing stock issues.']);
                }
            }
            $this->assertNextInChronology('pos_sale', $sale->id);
            $cashSource = match ($sale->payment_method) {
                'cash' => 'cash', 'card' => 'card_clearing', 'bank_transfer' => 'bank',
                default => throw ValidationException::withMessages(['source' => 'Unsupported saved POS payment type.']),
            };
            $accounts = collect([$cashSource, 'sales', 'output_vat', 'cogs', 'inventory'])
                ->mapWithKeys(fn (string $source) => [$source => $this->mappedAccount($source)]);
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'POS-GL-'.$sale->id, 'source_type' => 'pos_sale',
                'source_id' => (string) $sale->id, 'accounting_date' => $date->toDateString(), 'posting_period_id' => $period->id,
                'description' => 'Reconciled POS sale '.$sale->transaction_number, 'status' => 'draft', 'prepared_by' => $actorId,
            ]);
            foreach ([
                [$accounts[$cashSource], $total, 0], [$accounts['sales'], 0, $subtotal],
                [$accounts['output_vat'], 0, $vat], [$accounts['cogs'], $cogs, 0], [$accounts['inventory'], 0, $cogs],
            ] as [$account, $debit, $credit]) {
                $journal->lines()->create(['accounting_account_id' => $account->id, 'debit_cents' => $debit, 'credit_cents' => $credit]);
                $account->markUsed();
            }
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId,
                'approved_at' => now('UTC'), 'approved_by' => $actorId,
            ])->save();
            StockMovement::query()->whereIn('id', $stockMovements->modelKeys())->whereNull('accounting_journal_id')
                ->update(['accounting_journal_id' => $journal->id]);

            return $journal;
        });
    }

    public function includeStockMovement(int $movementId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($movementId, $actorId): AccountingJournal {
            $this->authorizeSourceReconciliation($actorId);
            $movement = StockMovement::query()->lockForUpdate()->findOrFail($movementId);
            if ($movement->type === 'opening_balance' || $movement->value_cents === null
                || $movement->carrying_value_after_cents === null || (int) $movement->value_cents === 0) {
                throw ValidationException::withMessages(['source' => 'Historical stock cost evidence is missing; do not infer it from current item cost.']);
            }
            if (AccountingJournal::query()->where('source_type', 'stock_movement')->where('source_id', (string) $movement->id)->exists()
                || $movement->accounting_journal_id !== null) {
                throw ValidationException::withMessages(['source' => 'This stock source already has an accounting link.']);
            }
            $date = $movement->effective_date->toDateString();
            $opening = $this->opening();
            if (! $opening || $date < $opening->accounting_date->toDateString() || $date > now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['source' => 'The stock event date is outside approved cutover coverage or is future-dated.']);
            }
            $period = $this->openPeriod($date);
            $this->assertNextInChronology('stock_movement', $movement->id);
            $inventory = $this->mappedAccount('inventory');
            $counterpart = $this->mappedAccount((int) $movement->value_cents > 0 ? 'recovery_offset' : 'adjustment');
            $amount = abs((int) $movement->value_cents);
            $inventoryDebit = (int) $movement->value_cents > 0 ? $amount : 0;
            $inventoryCredit = (int) $movement->value_cents < 0 ? $amount : 0;
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'STK-'.$movement->id, 'source_type' => 'stock_movement',
                'source_id' => (string) $movement->id, 'accounting_date' => $date, 'posting_period_id' => $period->id,
                'description' => 'Reconciled stock movement '.$movement->reference, 'status' => 'draft', 'prepared_by' => $actorId,
            ]);
            $journal->lines()->createMany([
                ['accounting_account_id' => $inventory->id, 'debit_cents' => $inventoryDebit, 'credit_cents' => $inventoryCredit],
                ['accounting_account_id' => $counterpart->id, 'debit_cents' => $inventoryCredit, 'credit_cents' => $inventoryDebit],
            ]);
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId,
                'approved_at' => now('UTC'), 'approved_by' => $actorId,
            ])->save();
            DB::table('stock_movements')->where('id', $movement->id)->whereNull('accounting_journal_id')
                ->update(['accounting_journal_id' => $journal->id]);
            $inventory->markUsed();
            $counterpart->markUsed();

            return $journal;
        });
    }

    public function includeSupplierPurchase(int $invoiceId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($invoiceId, $actorId): AccountingJournal {
            $this->authorizeSourceReconciliation($actorId);
            $invoice = SupplierPurchaseInvoice::query()->with('lines')->lockForUpdate()->findOrFail($invoiceId);
            if ($invoice->status !== 'posted' || ! $invoice->receipt_confirmed || $invoice->lines->isEmpty()) {
                throw ValidationException::withMessages(['source' => 'A completed received supplier purchase with allocations is required.']);
            }
            if (AccountingJournal::query()->where('source_type', 'supplier_purchase')->where('source_id', (string) $invoice->id)->exists()) {
                throw ValidationException::withMessages(['source' => 'This purchase already has an accounting link.']);
            }
            $date = $invoice->recognition_date->toDateString();
            $opening = $this->opening();
            if (! $opening || $date < $opening->accounting_date->toDateString() || $date > now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['source' => 'The purchase date is outside approved cutover coverage or is future-dated.']);
            }
            $period = $this->openPeriod($date);
            $this->assertNextInChronology('supplier_purchase', $invoice->id);
            $debits = (int) $invoice->lines->sum('line_amount_cents');
            if ($debits !== (int) $invoice->gross_amount_cents || $debits <= 0) {
                throw ValidationException::withMessages(['source' => 'Saved purchase allocations do not equal the source invoice amount.']);
            }
            if (! $this->purchaseReceiptEvidenceMatches($invoice)) {
                throw ValidationException::withMessages(['source' => 'The purchase receipt quantity/cost is unsupported or already accounted; no receipt may be recreated.']);
            }
            $journalLines = [];
            foreach ($invoice->lines as $line) {
                if ($line->inventory_id) {
                    $account = $this->mappedAccount('inventory');
                } else {
                    $account = AccountingAccount::query()->find($line->accounting_account_id);
                    if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Asset', 'Expense'], true)
                        || in_array($account->classification, ['accounts_payable', 'inventory', 'input_vat', 'output_vat'], true)) {
                        throw ValidationException::withMessages(['source' => 'The saved non-inventory purchase allocation is not an approved asset or expense.']);
                    }
                }
                $journalLines[] = ['accounting_account_id' => $account->id, 'debit_cents' => (int) $line->line_amount_cents, 'credit_cents' => 0];
            }
            $ap = $this->mappedAccount('accounts_payable');
            $journalLines[] = ['accounting_account_id' => $ap->id, 'debit_cents' => 0, 'credit_cents' => $debits];
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'PUR-'.$invoice->id, 'source_type' => 'supplier_purchase',
                'source_id' => (string) $invoice->id, 'accounting_date' => $date, 'posting_period_id' => $period->id,
                'description' => $invoice->description, 'status' => 'draft', 'prepared_by' => $actorId,
            ]);
            $journal->lines()->createMany($journalLines);
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId,
                'approved_at' => now('UTC'), 'approved_by' => $actorId,
            ])->save();
            DB::table('supplier_purchase_invoices')->where('id', $invoice->id)->whereNull('accounting_journal_id')
                ->update(['accounting_journal_id' => $journal->id]);
            DB::table('stock_movements')->where('source_reference', 'supplier_purchase:'.$invoice->id)
                ->whereNull('accounting_journal_id')->update(['accounting_journal_id' => $journal->id]);
            foreach ($journal->lines as $line) {
                AccountingAccount::query()->find($line->accounting_account_id)?->markUsed();
            }

            return $journal;
        });
    }

    public function includePayment(int $paymentId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($paymentId, $actorId): AccountingJournal {
            $this->authorizeSourceReconciliation($actorId);
            $payment = CashDisbursement::query()->with('lines')->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'posted' || $payment->reversal_of_id !== null || $payment->lines->isEmpty()) {
                throw ValidationException::withMessages(['source' => 'A completed payment with saved allocations is required.']);
            }
            if (AccountingJournal::query()->where('source_type', 'cash_disbursement')->where('source_id', (string) $payment->id)->exists()) {
                throw ValidationException::withMessages(['source' => 'This payment already has an accounting link.']);
            }
            $date = $payment->payment_date->toDateString();
            $opening = $this->opening();
            if (! $opening || $date < $opening->accounting_date->toDateString() || $date > now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['source' => 'The payment date is outside approved cutover coverage or is future-dated.']);
            }
            $period = $this->openPeriod($date);
            if ((int) $payment->lines->sum('amount_cents') !== (int) $payment->amount_cents) {
                throw ValidationException::withMessages(['source' => 'Saved payment allocations do not equal the payment amount.']);
            }
            $this->assertNextInChronology('cash_disbursement', $payment->id);
            $moneyAccount = $payment->money_account_id
                ? AccountingAccount::query()->find($payment->money_account_id)
                : null;
            if (! $moneyAccount?->isApprovedForPosting() || ! in_array($moneyAccount->type, ['Asset'], true)
                || ! in_array($moneyAccount->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['source' => 'The saved payment account is not an approved Cash or Bank account.']);
            }
            $journalLines = [];
            foreach ($payment->lines as $line) {
                $account = AccountingAccount::query()->find($line->accounting_account_id);
                if (! $account?->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['source' => 'A saved payment allocation uses an inactive or unapproved account.']);
                }
                if ($line->inventory_id) {
                    if ($account->classification !== 'inventory' || $this->mappedAccount('inventory')->id !== $account->id) {
                        throw ValidationException::withMessages(['source' => 'A direct-purchase receipt must use the approved Inventory mapping.']);
                    }
                    $movement = StockMovement::query()->where('cash_disbursement_line_id', $line->id)->first();
                    $expectedQuantity = (int) round((float) $line->quantity * 100);
                    $movementQuantity = $movement ? (int) round((float) $movement->quantity * 100) : 0;
                    if (! $movement || (int) $movement->value_cents !== (int) $line->amount_cents
                        || $movementQuantity !== $expectedQuantity || $movement->accounting_journal_id !== null) {
                        throw ValidationException::withMessages(['source' => 'The direct-purchase receipt quantity/cost is missing, unsupported or already accounted.']);
                    }
                }
                $journalLines[] = ['accounting_account_id' => $account->id, 'debit_cents' => (int) $line->amount_cents, 'credit_cents' => 0];
            }
            $journalLines[] = ['accounting_account_id' => $moneyAccount->id, 'debit_cents' => 0, 'credit_cents' => (int) $payment->amount_cents];
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC', 'reference' => 'CD-'.$payment->id, 'source_type' => 'cash_disbursement',
                'source_id' => (string) $payment->id, 'accounting_date' => $date, 'posting_period_id' => $period->id,
                'description' => $payment->description.' — '.$payment->payee, 'external_reference' => $payment->reference,
                'status' => 'draft', 'prepared_by' => $actorId,
            ]);
            $journal->lines()->createMany($journalLines);
            $journal->forceFill([
                'status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId,
                'approved_at' => now('UTC'), 'approved_by' => $actorId,
            ])->save();
            DB::table('cash_disbursements')->where('id', $payment->id)->whereNull('journal_id')->update(['journal_id' => $journal->id]);
            DB::table('stock_movements')->whereIn('cash_disbursement_line_id', $payment->lines->pluck('id'))
                ->whereNull('accounting_journal_id')->update(['accounting_journal_id' => $journal->id]);
            foreach ($journal->lines as $line) {
                AccountingAccount::query()->find($line->accounting_account_id)?->markUsed();
            }

            return $journal;
        });
    }

    private function posEvidenceMatches(PosTransaction $sale, bool $requireUnlinked = true): bool
    {
        if (! $sale->vatRecord || $sale->lines->isEmpty()) {
            return false;
        }
        $subtotal = $this->cents((string) $sale->subtotal);
        $vat = $this->cents((string) $sale->vat_amount);
        $total = $this->cents((string) $sale->total);
        if ($subtotal <= 0 || $vat < 0 || $total !== $subtotal + $vat
            || $this->cents((string) $sale->vatRecord->taxable_sales) !== $subtotal
            || $this->cents((string) $sale->vatRecord->output_vat) !== $vat
            || $this->cents((string) $sale->vatRecord->total) !== $total
            || $sale->lines->contains(fn ($line) => $line->line_cost_cents === null || (int) $line->line_cost_cents <= 0)) {
            return false;
        }
        $movements = StockMovement::query()->where('source_reference', 'pos_sale:'.$sale->id)->get();
        if ($movements->isEmpty() || $movements->contains(fn (StockMovement $movement) => $movement->value_cents === null
            || $movement->carrying_value_after_cents === null || ($requireUnlinked && $movement->accounting_journal_id !== null))) {
            return false;
        }
        foreach ($sale->lines->groupBy('inventory_id') as $inventoryId => $lines) {
            $itemMovements = $movements->where('inventory_id', (int) $inventoryId);
            $lineCost = (int) $lines->sum('line_cost_cents');
            $movementCost = (int) $itemMovements->sum('value_cents');
            $lineQuantity = (int) round($lines->sum(fn ($line) => (float) $line->quantity) * 100);
            $movementQuantity = (int) round($itemMovements->sum(fn (StockMovement $movement) => (float) $movement->quantity) * 100);
            if ($itemMovements->isEmpty() || $movementCost !== -$lineCost || $movementQuantity !== -$lineQuantity) {
                return false;
            }
        }

        return true;
    }

    private function purchaseEvidenceMatches(SupplierPurchaseInvoice $invoice): bool
    {
        if ($invoice->lines->isEmpty() || (int) $invoice->lines->sum('line_amount_cents') !== (int) $invoice->gross_amount_cents) {
            return false;
        }
        if (! $this->purchaseReceiptEvidenceMatches($invoice, false)) {
            return false;
        }
        foreach ($invoice->lines->whereNull('inventory_id') as $line) {
            $account = AccountingAccount::query()->find($line->accounting_account_id);
            if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Asset', 'Expense'], true)
                || in_array($account->classification, ['accounts_payable', 'inventory', 'input_vat', 'output_vat'], true)) {
                return false;
            }
        }

        return true;
    }

    private function purchaseReceiptEvidenceMatches(SupplierPurchaseInvoice $invoice, bool $requireUnlinked = true): bool
    {
        foreach ($invoice->lines->whereNotNull('inventory_id')->groupBy('inventory_id') as $inventoryId => $lines) {
            $movements = StockMovement::query()->where('source_reference', 'supplier_purchase:'.$invoice->id)
                ->where('inventory_id', $inventoryId)->get();
            $expectedAmount = (int) $lines->sum('line_amount_cents');
            $expectedQuantity = (int) round($lines->sum(fn ($line) => (float) $line->quantity) * 100);
            $movementQuantity = (int) round($movements->sum(fn (StockMovement $movement) => (float) $movement->quantity) * 100);
            if ($movements->isEmpty() || $movements->contains(fn (StockMovement $movement) => $movement->value_cents === null
                || $movement->carrying_value_after_cents === null || ($requireUnlinked && $movement->accounting_journal_id !== null))
                || (int) $movements->sum('value_cents') !== $expectedAmount || $movementQuantity !== $expectedQuantity) {
                return false;
            }
        }

        return true;
    }

    private function paymentEvidenceMatches(CashDisbursement $payment, bool $requireUnlinked = true): bool
    {
        if ($payment->lines->isEmpty() || (int) $payment->lines->sum('amount_cents') !== (int) $payment->amount_cents) {
            return false;
        }
        $moneyAccount = $payment->money_account_id ? AccountingAccount::query()->find($payment->money_account_id) : null;
        if (! $moneyAccount?->isApprovedForPosting() || $moneyAccount->type !== 'Asset'
            || ! in_array($moneyAccount->classification, ['cash', 'bank'], true)) {
            return false;
        }
        foreach ($payment->lines as $line) {
            $account = AccountingAccount::query()->find($line->accounting_account_id);
            if (! $account?->isApprovedForPosting()) {
                return false;
            }
            if ($line->inventory_id) {
                if ($account->classification !== 'inventory' || ! AccountingPostingMapping::query()
                    ->where('source', 'inventory')->where('accounting_account_id', $account->id)->whereNotNull('approved_at')->exists()) {
                    return false;
                }
                $movement = StockMovement::query()->where('cash_disbursement_line_id', $line->id)->first();
                if (! $movement || (int) $movement->value_cents !== (int) $line->amount_cents
                    || (int) round((float) $movement->quantity * 100) !== (int) round((float) $line->quantity * 100)
                    || $movement->carrying_value_after_cents === null || ($requireUnlinked && $movement->accounting_journal_id !== null)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function assertNextInChronology(string $type, int $id): void
    {
        $next = $this->sources()->first(fn (array $source) => $source['status'] !== 'matched');
        if ($next && ($next['type'] !== $type || $next['id'] !== $id)) {
            throw ValidationException::withMessages([
                'source' => 'Include post-cutover source activity in chronological order; resolve the earliest source first.',
            ]);
        }
    }

    private function row(string $type, int $id, string $date, string $reference, string $description, bool $hasEvidence): array
    {
        $sourceJournal = AccountingJournal::query()->where('source_type', $type)->where('source_id', (string) $id)->first();
        $stockMovement = $type === 'stock_movement' ? StockMovement::query()->find($id) : null;
        $linkedJournalId = $stockMovement?->accounting_journal_id;
        if ($type === 'pos_sale') {
            $linkedJournalId = StockMovement::query()->where('source_reference', 'pos_sale:'.$id)
                ->whereNotNull('accounting_journal_id')->value('accounting_journal_id');
        } elseif ($type === 'supplier_purchase') {
            $linkedJournalId = SupplierPurchaseInvoice::query()->whereKey($id)->value('accounting_journal_id');
        } elseif ($type === 'cash_disbursement') {
            $linkedJournalId = CashDisbursement::query()->whereKey($id)->value('journal_id');
        } elseif ($type === 'cash_disbursement_reversal') {
            $linkedJournalId = CashDisbursement::query()->where('reversal_of_id', $id)->value('journal_id');
        }
        $linkedJournal = $linkedJournalId ? AccountingJournal::query()->find($linkedJournalId) : null;
        $linkedIdentityMatches = match ($type) {
            'stock_movement' => $linkedJournal?->source_type === 'stock_movement'
                && $linkedJournal->source_id === (string) $id,
            default => $linkedJournal?->source_type === $type && $linkedJournal->source_id === (string) $id,
        };
        if ($type === 'stock_movement' && $linkedJournal) {
            $linkedIdentityMatches = $linkedIdentityMatches
                || ($stockMovement?->source_reference === 'pos_sale:'.$linkedJournal->source_id && $linkedJournal->source_type === 'pos_sale')
                || ($stockMovement?->source_reference === 'supplier_purchase:'.$linkedJournal->source_id && $linkedJournal->source_type === 'supplier_purchase')
                || ($stockMovement?->source_reference === 'direct_purchase:'.$linkedJournal->source_id && $linkedJournal->source_type === 'cash_disbursement');
        }
        $journal = $sourceJournal ?? ($linkedJournalId ? AccountingJournal::query()->find($linkedJournalId) : null);
        if ($sourceJournal && $sourceJournal->status !== 'posted') {
            $status = 'duplicate-link';
            $reason = 'A non-posted journal already occupies this source identity.';
        } elseif ($sourceJournal && $linkedJournalId && (int) $linkedJournalId !== $sourceJournal->id) {
            $status = 'duplicate-link';
            $reason = 'The source and its operational record point to different accounting journals.';
        } elseif ($sourceJournal && ! $this->sourceLinksMatch($type, $id, $sourceJournal)) {
            $status = 'duplicate-link';
            $reason = 'The posted journal is missing an operational source link.';
        } elseif (! $sourceJournal && $linkedJournal && ! $linkedIdentityMatches) {
            $status = 'duplicate-link';
            $reason = 'The operational record points to an unrelated accounting journal.';
        } elseif ($journal && $type !== 'cash_disbursement_reversal' && ! $hasEvidence) {
            $status = 'unsupported-cost';
            $reason = 'Historical cost, allocation or linked receipt evidence is incomplete.';
        } elseif ($journal) {
            $status = 'matched';
            $reason = null;
        } elseif (in_array($type, ['pos_sale', 'stock_movement', 'supplier_purchase', 'cash_disbursement'], true) && $hasEvidence) {
            $status = 'missing';
            $reason = null;
        } else {
            $status = 'unsupported-cost';
            $reason = 'Historical cost, allocation or linked receipt evidence is incomplete.';
        }

        return compact('type', 'id', 'date', 'reference', 'description', 'status', 'reason') + ['journal_id' => $journal?->id];
    }

    private function sourceLinksMatch(string $type, int $id, AccountingJournal $journal): bool
    {
        return match ($type) {
            'pos_sale' => $this->linkedMovementsMatch('pos_sale:'.$id, $journal->id, true),
            'supplier_purchase' => $this->supplierPurchaseLinksMatch($id, $journal->id),
            'cash_disbursement' => $this->paymentLinksMatch($id, $journal->id),
            'cash_disbursement_reversal' => (int) CashDisbursement::query()->where('reversal_of_id', $id)->value('journal_id') === $journal->id,
            'stock_movement' => (int) StockMovement::query()->whereKey($id)->value('accounting_journal_id') === $journal->id,
            default => false,
        };
    }

    private function supplierPurchaseLinksMatch(int $invoiceId, int $journalId): bool
    {
        $invoice = SupplierPurchaseInvoice::query()->with('lines')->find($invoiceId);
        if (! $invoice || (int) $invoice->accounting_journal_id !== $journalId) {
            return false;
        }
        if (! $invoice->lines->contains(fn ($line) => $line->inventory_id !== null)) {
            return true;
        }

        return $this->linkedMovementsMatch('supplier_purchase:'.$invoiceId, $journalId, true);
    }

    private function paymentLinksMatch(int $paymentId, int $journalId): bool
    {
        $payment = CashDisbursement::query()->with('lines')->find($paymentId);
        if (! $payment || (int) $payment->journal_id !== $journalId) {
            return false;
        }
        $lineIds = $payment->lines->whereNotNull('inventory_id')->pluck('id');
        if ($lineIds->isEmpty()) {
            return true;
        }
        $movements = StockMovement::query()->whereIn('cash_disbursement_line_id', $lineIds)->get();

        return $movements->count() === $lineIds->count()
            && $movements->every(fn (StockMovement $movement) => (int) $movement->accounting_journal_id === $journalId
                && $movement->value_cents !== null && $movement->carrying_value_after_cents !== null);
    }

    private function linkedMovementsMatch(string $sourceReference, int $journalId, bool $requireMovements): bool
    {
        $movements = StockMovement::query()->where('source_reference', $sourceReference)->get();

        return (! $requireMovements || $movements->isNotEmpty())
            && $movements->every(fn (StockMovement $movement) => (int) $movement->accounting_journal_id === $journalId
                && $movement->value_cents !== null && $movement->carrying_value_after_cents !== null);
    }

    private function authorizeSourceReconciliation(int $actorId): void
    {
        $actor = Employee::query()->findOrFail($actorId);
        abort_unless($actor->can('accounting.reconcile-sources')
            && $actor->can('accounting.post-reconciled-sources'), 403);
    }

    private function opening(): ?AccountingJournal
    {
        return AccountingJournal::query()->where('book_key', 'FCDC')->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->first();
    }

    private function openPeriod(string $date): AccountingPostingPeriod
    {
        $period = AccountingPostingPeriod::query()->where('book_key', 'FCDC')->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)->lockForUpdate()->first();
        if (! $period || $period->status !== 'open') {
            throw ValidationException::withMessages(['source' => 'The source accounting date must be in an open posting period.']);
        }

        return $period;
    }

    private function mappedAccount(string $source): AccountingAccount
    {
        $mapping = AccountingPostingMapping::query()->where('source', $source)->with('account')->first();
        if (! $mapping?->isApprovedForPosting()) {
            throw ValidationException::withMessages(['source' => 'Approved active posting mappings are required before source inclusion.']);
        }

        return $mapping->account;
    }

    private function cents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
