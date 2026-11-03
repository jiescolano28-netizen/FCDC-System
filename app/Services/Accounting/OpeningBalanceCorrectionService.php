<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningBalanceCorrection;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\SupplierOpeningInvoice;
use App\Support\ActivityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpeningBalanceCorrectionService
{
    public function request(array $input, Employee $actor): OpeningBalanceCorrection
    {
        $data = Validator::make($input, [
            'type' => ['required', 'in:journal,inventory,supplier_ap'],
            'reason' => ['required', 'string', 'max:4000'],
            'details' => ['required', 'array'],
        ])->validate();

        return DB::transaction(function () use ($data, $actor): OpeningBalanceCorrection {
            $opening = $this->postedOpeningJournal(true);
            $date = now('Asia/Manila')->toDateString();
            if ($date < $opening->accounting_date->toDateString()) {
                throw ValidationException::withMessages(['correction' => 'Correction date cannot precede the approved cutover date.']);
            }
            if (OpeningBalanceCorrection::query()->where('opening_journal_id', $opening->id)
                ->whereIn('status', ['pending_review', 'approved'])->exists()) {
                throw ValidationException::withMessages(['correction' => 'Review or post the existing opening-balance correction request before submitting another.']);
            }

            $details = match ($data['type']) {
                'journal' => $this->journalDetails($data['details']),
                'inventory' => $this->inventoryDetails($data['details']),
                'supplier_ap' => $this->supplierDetails($data['details']),
            };

            $request = OpeningBalanceCorrection::query()->create([
                'opening_journal_id' => $opening->id,
                'correction_type' => $data['type'],
                'status' => 'pending_review',
                'posting_date' => $date,
                'reason' => trim($data['reason']),
                'details' => $details,
                'prepared_by' => $actor->id,
            ]);
            ActivityAudit::recordChange($request, 'Opening balance correction requested', [
                'type' => $request->correction_type,
                'reason' => $request->reason,
                'details' => $details,
                'opening_journal_id' => $opening->id,
            ]);

            return $request->load('preparer');
        });
    }

    public function approve(int $requestId, string $note, Employee $reviewer): OpeningBalanceCorrection
    {
        return DB::transaction(function () use ($requestId, $note, $reviewer): OpeningBalanceCorrection {
            $request = OpeningBalanceCorrection::query()->lockForUpdate()->findOrFail($requestId);
            $this->assertReviewable($request, $reviewer);
            if (trim($note) !== '' && mb_strlen($note) > 4000) {
                throw ValidationException::withMessages(['reviewNote' => 'Review note cannot exceed 4,000 characters.']);
            }
            $request->forceFill([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => trim($note) ?: null,
            ])->save();
            ActivityAudit::recordChange($request, 'Opening balance correction approved', [
                'status' => 'approved', 'reviewed_by' => $reviewer->id, 'review_note' => $request->review_note,
            ], ['status' => 'pending_review']);

            return $request->refresh()->load(['preparer', 'reviewer']);
        });
    }

    public function reject(int $requestId, string $note, Employee $reviewer): OpeningBalanceCorrection
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 4000) {
            throw ValidationException::withMessages(['reviewNote' => 'Provide a rejection reason of at most 4,000 characters.']);
        }

        return DB::transaction(function () use ($requestId, $note, $reviewer): OpeningBalanceCorrection {
            $request = OpeningBalanceCorrection::query()->lockForUpdate()->findOrFail($requestId);
            $this->assertReviewable($request, $reviewer);
            $request->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now('UTC'),
                'review_note' => $note,
            ])->save();
            ActivityAudit::recordChange($request, 'Opening balance correction rejected', [
                'status' => 'rejected', 'reviewed_by' => $reviewer->id, 'review_note' => $note,
            ], ['status' => 'pending_review']);

            return $request->refresh()->load(['preparer', 'reviewer']);
        });
    }

    public function post(int $requestId, Employee $actor): AccountingJournal
    {
        return DB::transaction(function () use ($requestId, $actor): AccountingJournal {
            $request = OpeningBalanceCorrection::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== 'approved' || ! $request->reviewed_by) {
                throw ValidationException::withMessages(['correction' => 'Only an approved correction request can be posted.']);
            }
            if ($request->prepared_by === $actor->id) {
                throw ValidationException::withMessages(['correction' => 'The requester cannot post their own correction.']);
            }
            $opening = $this->postedOpeningJournal(true);
            if ((int) $opening->id !== (int) $request->opening_journal_id) {
                throw ValidationException::withMessages(['correction' => 'The linked opening journal has changed; request a new review.']);
            }
            $postingDate = now('Asia/Manila')->toDateString();
            if ($postingDate < $opening->accounting_date->toDateString()) {
                throw ValidationException::withMessages(['correction' => 'Correction date cannot precede the approved cutover date.']);
            }
            $period = app(AccountingPeriodService::class)->lockOpenPeriodForDate(
                $postingDate,
                'correction',
                'The correction posting date is in a closed accounting period.',
            );
            $lines = match ($request->correction_type) {
                'journal' => $request->details['lines'],
                'inventory' => $this->inventoryJournalLines($request),
                'supplier_ap' => $this->supplierJournalLines($request),
                default => throw ValidationException::withMessages(['correction' => 'Unsupported opening correction type.']),
            };
            $this->assertBalanced($lines);
            $this->assertPostingAccounts($lines, $request->correction_type);

            $reference = 'OPEN-CORR-'.$request->id.'-'.Str::upper(Str::random(6));
            $journal = AccountingJournal::query()->create([
                'book_key' => $opening->book_key,
                'reference' => $reference,
                'source_type' => 'opening_balance_correction',
                'source_id' => (string) $request->id,
                'entry_type' => AccountingJournal::ENTRY_TYPE_ADJUSTING,
                'manual_input' => false,
                'accounting_date' => $postingDate,
                'posting_period_id' => $period->id,
                'description' => 'Opening balance correction: '.$request->reason,
                'external_reference' => $opening->reference,
                'correction_of_id' => $opening->id,
                'correction_reason' => $request->reason,
                'status' => 'draft',
                'prepared_by' => $request->prepared_by,
                'approved_by' => $request->reviewed_by,
                'approved_at' => $request->reviewed_at,
                'posted_by' => $actor->id,
                'posted_at' => now('UTC'),
            ]);
            $journal->lines()->createMany($lines);
            $journal->forceFill(['status' => 'posted'])->save();
            foreach ($journal->lines()->with('account')->get() as $line) {
                $line->account?->markUsed();
            }
            $this->applyScheduleCorrection($request, $journal, $actor);
            $request->forceFill([
                'status' => 'posted', 'posting_date' => $postingDate, 'journal_id' => $journal->id,
                'posted_by' => $actor->id, 'posted_at' => now('UTC'),
            ])->save();
            ActivityAudit::recordChange($request, 'Opening balance correction posted', [
                'status' => 'posted', 'journal_id' => $journal->id, 'posted_by' => $actor->id,
            ], ['status' => 'approved']);

            return $journal->load('lines.account', 'correctionOf');
        });
    }

    private function journalDetails(array $input): array
    {
        $raw = $input['lines'] ?? [];
        if (! is_array($raw) || count($raw) < 2) {
            throw ValidationException::withMessages(['correction' => 'Enter at least two balanced adjustment lines.']);
        }
        $lines = [];
        foreach ($raw as $index => $line) {
            $account = $this->approvedAccount($line['accountId'] ?? null, "details.lines.$index.accountId");
            if (in_array($account->classification, ['inventory', 'accounts_payable'], true)) {
                throw ValidationException::withMessages(["details.lines.$index.accountId" => 'Use the Inventory or Supplier/AP correction type for control-account changes.']);
            }
            $debit = $this->amountInCents($line['debit'] ?? '');
            $credit = $this->amountInCents($line['credit'] ?? '');
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["details.lines.$index" => 'Each adjustment line must have exactly one positive debit or credit.']);
            }
            $lines[] = ['accounting_account_id' => $account->id, 'debit_cents' => $debit, 'credit_cents' => $credit];
        }
        $this->assertBalanced($lines);

        return ['lines' => $lines];
    }

    private function inventoryDetails(array $input): array
    {
        $valuation = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')->with('lines')->lockForUpdate()->firstOrFail();
        $target = $input['lines'] ?? [];
        if (! is_array($target) || count($target) !== $valuation->lines->count()) {
            throw ValidationException::withMessages(['correction' => 'Include every item in the approved opening inventory valuation.']);
        }
        $counter = $this->approvedAccount($input['counterAccountId'] ?? null, 'details.counterAccountId');
        if (in_array($counter->classification, ['inventory', 'accounts_payable'], true)) {
            throw ValidationException::withMessages(['correction' => 'Select an approved non-control counterpart account.']);
        }
        $account = AccountingAccount::query()->where('classification', 'inventory')->where('is_active', true)->whereNotNull('approved_at')->orderBy('code')->firstOrFail();
        $original = $valuation->lines->keyBy('inventory_id');
        $priorDeltas = $this->postedCorrections('inventory')->flatMap(fn (OpeningBalanceCorrection $correction) => $correction->details['lines'] ?? [])
            ->groupBy('inventory_id')->map(fn ($rows) => (int) $rows->sum('delta_cents'));
        $seen = [];
        $lines = [];
        foreach ($target as $index => $row) {
            $inventoryId = filter_var($row['inventoryId'] ?? null, FILTER_VALIDATE_INT);
            $base = $inventoryId ? $original->get($inventoryId) : null;
            if (! $base || isset($seen[$inventoryId])) {
                throw ValidationException::withMessages(["details.lines.$index.inventoryId" => 'Select each approved opening inventory item exactly once.']);
            }
            $seen[$inventoryId] = true;
            $newValue = $this->amountInCents($row['value'] ?? '');
            $oldValue = (int) $base->carrying_value_cents + (int) $priorDeltas->get($inventoryId, 0);
            $delta = $newValue - $oldValue;
            if ($delta === 0) {
                continue;
            }
            if ($newValue > 0 && (float) $base->quantity <= 0) {
                throw ValidationException::withMessages(["details.lines.$index.value" => 'An item with no opening quantity cannot carry an opening value.']);
            }
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);
            if ((int) $item->carrying_value_cents + $delta < 0) {
                throw ValidationException::withMessages(["details.lines.$index.value" => 'The correction cannot reduce current inventory carrying value below zero.']);
            }
            $lines[] = ['inventory_id' => (int) $inventoryId, 'old_value_cents' => $oldValue, 'new_value_cents' => $newValue, 'delta_cents' => $delta];
        }
        if (count($seen) !== $original->count() || $lines === []) {
            throw ValidationException::withMessages(['correction' => 'Make at least one supported carrying-value change and include every opening item.']);
        }

        return ['lines' => $lines, 'counter_account_id' => $counter->id, 'inventory_account_id' => $account->id];
    }

    private function supplierDetails(array $input): array
    {
        $invoices = SupplierOpeningInvoice::query()->where('status', 'posted')->where('opening_journal_id', $this->postedOpeningJournal()->id)->get()->keyBy('id');
        $changes = $input['lines'] ?? [];
        if (! is_array($changes) || $changes === []) {
            throw ValidationException::withMessages(['correction' => 'Enter at least one supplier invoice correction.']);
        }
        $counter = $this->approvedAccount($input['counterAccountId'] ?? null, 'details.counterAccountId');
        if (in_array($counter->classification, ['inventory', 'accounts_payable'], true)) {
            throw ValidationException::withMessages(['correction' => 'Select an approved non-control counterpart account.']);
        }
        $ap = AccountingAccount::query()->where('classification', 'accounts_payable')->where('is_active', true)->whereNotNull('approved_at')->orderBy('code')->firstOrFail();
        $prior = $this->postedCorrections('supplier_ap')->flatMap(fn (OpeningBalanceCorrection $correction) => $correction->details['lines'] ?? [])
            ->groupBy('supplier_opening_invoice_id')->map(fn ($lines) => (int) $lines->sum('delta_cents'));
        $normalized = [];
        $seen = [];
        foreach ($changes as $index => $row) {
            $invoiceId = filter_var($row['invoiceId'] ?? null, FILTER_VALIDATE_INT);
            $invoice = $invoiceId ? $invoices->get($invoiceId) : null;
            if (! $invoice || isset($seen[$invoiceId])) {
                throw ValidationException::withMessages(["details.lines.$index.invoiceId" => 'Select each posted opening invoice at most once.']);
            }
            $seen[$invoiceId] = true;
            $newAmount = $this->amountInCents($row['amount'] ?? '');
            if ($newAmount < 1) {
                throw ValidationException::withMessages(["details.lines.$index.amount" => 'A corrected opening invoice amount must remain positive.']);
            }
            $oldAmount = (int) $invoice->amount_cents + (int) $prior->get($invoiceId, 0);
            $delta = $newAmount - $oldAmount;
            if ($newAmount < $invoice->paidAmountCents()) {
                throw ValidationException::withMessages(["details.lines.$index.amount" => 'Corrected invoice amount cannot be below posted payments already allocated to it.']);
            }
            if ($delta !== 0) {
                $normalized[] = [
                    'supplier_opening_invoice_id' => (int) $invoiceId,
                    'supplier_id' => (int) $invoice->supplier_id,
                    'invoice_number' => $invoice->invoice_number,
                    'old_amount_cents' => $oldAmount,
                    'new_amount_cents' => $newAmount,
                    'delta_cents' => $delta,
                ];
            }
        }
        if ($normalized === []) {
            throw ValidationException::withMessages(['correction' => 'Enter at least one changed supplier invoice amount.']);
        }

        return ['lines' => $normalized, 'counter_account_id' => $counter->id, 'ap_account_id' => $ap->id];
    }

    private function inventoryJournalLines(OpeningBalanceCorrection $request): array
    {
        $delta = (int) collect($request->details['lines'])->sum('delta_cents');
        $inventory = (int) $request->details['inventory_account_id'];
        $counter = (int) $request->details['counter_account_id'];
        if ($delta === 0) {
            $increase = (int) collect($request->details['lines'])->where('delta_cents', '>', 0)->sum('delta_cents');
            if ($increase > 0) {
                return [
                    ['accounting_account_id' => $inventory, 'debit_cents' => $increase, 'credit_cents' => 0],
                    ['accounting_account_id' => $inventory, 'debit_cents' => 0, 'credit_cents' => $increase],
                ];
            }
        }

        return $this->twoSidedDelta($inventory, $counter, $delta);
    }

    private function supplierJournalLines(OpeningBalanceCorrection $request): array
    {
        foreach ($request->details['lines'] as $line) {
            $invoice = SupplierOpeningInvoice::query()->lockForUpdate()->findOrFail($line['supplier_opening_invoice_id']);
            if ((int) $line['new_amount_cents'] < $invoice->paidAmountCents()) {
                throw ValidationException::withMessages(['correction' => 'A corrected supplier invoice amount is now below payments already allocated to it.']);
            }
        }
        $delta = (int) collect($request->details['lines'])->sum('delta_cents');
        $ap = (int) $request->details['ap_account_id'];
        $counter = (int) $request->details['counter_account_id'];
        if ($delta === 0) {
            $increase = (int) collect($request->details['lines'])->where('delta_cents', '>', 0)->sum('delta_cents');
            if ($increase > 0) {
                return [
                    ['accounting_account_id' => $ap, 'debit_cents' => $increase, 'credit_cents' => 0],
                    ['accounting_account_id' => $ap, 'debit_cents' => 0, 'credit_cents' => $increase],
                ];
            }
        }

        return $this->twoSidedDelta($counter, $ap, $delta);
    }

    private function twoSidedDelta(int $debitAccount, int $creditAccount, int $delta): array
    {
        if ($delta === 0) {
            throw ValidationException::withMessages(['correction' => 'The requested correction has no net accounting effect.']);
        }
        if ($delta > 0) {
            return [
                ['accounting_account_id' => $debitAccount, 'debit_cents' => $delta, 'credit_cents' => 0],
                ['accounting_account_id' => $creditAccount, 'debit_cents' => 0, 'credit_cents' => $delta],
            ];
        }

        return [
            ['accounting_account_id' => $debitAccount, 'debit_cents' => 0, 'credit_cents' => abs($delta)],
            ['accounting_account_id' => $creditAccount, 'debit_cents' => abs($delta), 'credit_cents' => 0],
        ];
    }

    private function applyScheduleCorrection(OpeningBalanceCorrection $request, AccountingJournal $journal, Employee $actor): void
    {
        if ($request->correction_type === 'inventory') {
            foreach ($request->details['lines'] as $line) {
                $item = Inventory::query()->lockForUpdate()->findOrFail($line['inventory_id']);
                $latest = StockMovement::query()->where('inventory_id', $item->id)->whereNotNull('value_cents')
                    ->whereDate('effective_date', '<=', $journal->accounting_date)->orderByDesc('effective_date')->orderByDesc('posted_at')->orderByDesc('id')->first();
                $previousValue = $latest ? (int) $latest->carrying_value_after_cents : (int) $item->carrying_value_cents;
                $newCarrying = $previousValue + (int) $line['delta_cents'];
                if ($newCarrying < 0 || ((float) $item->qty <= 0 && $newCarrying !== 0)) {
                    throw ValidationException::withMessages(['correction' => 'The correction cannot reduce current inventory carrying value below zero.']);
                }
                $quantity = (float) $item->qty;
                StockMovement::query()->create([
                    'inventory_id' => $item->id,
                    'posted_by' => $actor->id,
                    'type' => 'valuation_correction',
                    'quantity' => '0.00',
                    'reason_category' => 'opening_balance_correction',
                    'notes' => $request->reason,
                    'reference' => 'OPEN-CORR-'.$request->id.'-'.$item->id,
                    'source_reference' => 'opening_balance_correction:'.$request->id,
                    'effective_date' => $journal->accounting_date->toDateString(),
                    'posted_at' => now('UTC'),
                    'value_cents' => (int) $line['delta_cents'],
                    'carrying_value_after_cents' => $newCarrying,
                    'accounting_journal_id' => $journal->id,
                    'correction_reason' => $request->reason,
                    'correction_total_delta_cents' => (int) $line['delta_cents'],
                ]);
                Inventory::query()->whereKey($item->id)->update([
                    'carrying_value_cents' => $newCarrying,
                    'unit_cost' => $quantity > 0 ? number_format(($newCarrying / 100) / $quantity, 2, '.', '') : '0.00',
                ]);
            }
        }
    }

    private function assertReviewable(OpeningBalanceCorrection $request, Employee $reviewer): void
    {
        if ($request->status !== 'pending_review') {
            throw ValidationException::withMessages(['correction' => 'Only a pending correction request can be reviewed.']);
        }
        if ((int) $request->prepared_by === (int) $reviewer->id) {
            throw ValidationException::withMessages(['correction' => 'The requester cannot review or approve their own correction.']);
        }
        $this->postedOpeningJournal(true);
    }

    private function postedOpeningJournal(bool $lock = false): AccountingJournal
    {
        $query = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    private function approvedAccount(mixed $id, string $errorKey): AccountingAccount
    {
        $validated = filter_var($id, FILTER_VALIDATE_INT);
        $account = $validated ? AccountingAccount::query()->lockForUpdate()->find($validated) : null;
        if (! $account?->isApprovedForPosting()) {
            throw ValidationException::withMessages([$errorKey => 'Select an active approved account.']);
        }

        return $account;
    }

    private function assertPostingAccounts(array $lines, string $type): void
    {
        foreach ($lines as $index => $line) {
            $account = AccountingAccount::query()->lockForUpdate()->find($line['accounting_account_id']);
            if (! $account?->isApprovedForPosting()) {
                throw ValidationException::withMessages(["correction.lines.$index" => 'Every correction account must remain active and approved.']);
            }
            if ($account->code === '3040') {
                throw ValidationException::withMessages(["correction.lines.$index" => 'Current-year earnings are derived in reporting and cannot be posted directly.']);
            }
            if ($type === 'journal' && in_array($account->classification, ['inventory', 'accounts_payable'], true)) {
                throw ValidationException::withMessages(["correction.lines.$index" => 'Inventory and Accounts Payable corrections must use their linked schedules.']);
            }
        }
    }

    private function assertBalanced(array $lines): void
    {
        $debits = (int) collect($lines)->sum('debit_cents');
        $credits = (int) collect($lines)->sum('credit_cents');
        if ($debits < 1 || $debits !== $credits) {
            throw ValidationException::withMessages(['correction' => 'Correction journal entries must have equal positive debits and credits.']);
        }
    }

    private function postedCorrections(string $type)
    {
        return OpeningBalanceCorrection::query()->where('correction_type', $type)->where('status', 'posted')->get();
    }

    private function amountInCents(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['correction' => 'Amounts must be entered in PHP with no more than two decimal places.']);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,2}))?$/D', $value)) {
            throw ValidationException::withMessages(['correction' => 'Amounts must be non-negative PHP amounts with no more than two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen($whole) > 12) {
            throw ValidationException::withMessages(['correction' => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
