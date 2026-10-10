<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\CashDisbursement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CashDisbursementService
{
    public function saveDraft(array $input, int $actorId, ?int $disbursementId = null): CashDisbursement
    {
        $validated = $this->validateDraft($input);

        return DB::transaction(function () use ($validated, $actorId, $disbursementId): CashDisbursement {
            $disbursement = $disbursementId
                ? CashDisbursement::query()->where('status', 'draft')->lockForUpdate()->findOrFail($disbursementId)
                : new CashDisbursement;
            if ($disbursement->reversal_of_id !== null) {
                throw ValidationException::withMessages(['disbursement' => 'A correction record cannot be edited.']);
            }
            $period = $this->periodFor($validated['paymentDate']);
            $disbursement->fill([
                'reference' => $validated['reference'],
                'payee' => $validated['payee'],
                'payment_date' => $validated['paymentDate'],
                'method' => $validated['method'],
                'check_number' => $validated['checkNumber'] ?: null,
                'money_account_id' => $validated['moneyAccountId'],
                'description' => $validated['description'],
                'evidence_reference' => $validated['evidenceReference'],
                'amount_cents' => $validated['amountCents'],
                'status' => 'draft',
                'posting_period_id' => $period->id,
                'prepared_by' => $actorId,
            ])->save();
            $disbursement->lines()->delete();
            foreach ($validated['allocations'] as $line) {
                $disbursement->lines()->create($line);
            }

            return $disbursement->load(['lines.account', 'moneyAccount']);
        });
    }

    public function deleteDraft(int $disbursementId): void
    {
        DB::transaction(function () use ($disbursementId): void {
            $disbursement = CashDisbursement::query()->where('status', 'draft')
                ->whereNull('reversal_of_id')->lockForUpdate()->findOrFail($disbursementId);
            $disbursement->lines()->delete();
            $disbursement->delete();
        });
    }

    public function post(int $disbursementId, int $actorId): CashDisbursement
    {
        return DB::transaction(function () use ($disbursementId, $actorId): CashDisbursement {
            $disbursement = CashDisbursement::query()->where('status', 'draft')->whereNull('reversal_of_id')
                ->lockForUpdate()->with(['lines.account', 'moneyAccount'])->findOrFail($disbursementId);
            $this->assertPostingEligible($disbursement);
            $date = $disbursement->payment_date->toDateString();
            $period = $this->periodFor($date);
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'CD-'.$disbursement->id,
                'source_type' => 'cash_disbursement',
                'source_id' => (string) $disbursement->id,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => $disbursement->description.' — '.$disbursement->payee,
                'external_reference' => $disbursement->reference,
                'status' => 'draft',
                'prepared_by' => $disbursement->prepared_by,
            ]);
            $journalLines = $disbursement->lines->map(fn ($line) => [
                'accounting_account_id' => $line->accounting_account_id,
                'debit_cents' => $line->amount_cents,
                'credit_cents' => 0,
            ])->all();
            $journalLines[] = [
                'accounting_account_id' => $disbursement->money_account_id,
                'debit_cents' => 0,
                'credit_cents' => $disbursement->amount_cents,
            ];
            $journal->lines()->createMany($journalLines);
            $journal->forceFill(['status' => 'posted', 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();
            foreach ($disbursement->lines as $line) {
                $line->account->markUsed();
            }
            $disbursement->moneyAccount->markUsed();
            $disbursement->forceFill(['status' => 'posted', 'journal_id' => $journal->id, 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();

            return $disbursement->refresh()->load(['lines.account', 'moneyAccount', 'journal']);
        });
    }

    public function reverse(int $disbursementId, string $reason, int $actorId): CashDisbursement
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 4000) {
            throw ValidationException::withMessages(['reversalReason' => 'Provide a reason of at most 4,000 characters.']);
        }

        return DB::transaction(function () use ($disbursementId, $reason, $actorId): CashDisbursement {
            $original = CashDisbursement::query()->where('status', 'posted')->whereNull('reversal_of_id')
                ->lockForUpdate()->with(['lines.account', 'moneyAccount', 'journal.lines'])->findOrFail($disbursementId);
            if ($original->reversals()->exists()) {
                throw ValidationException::withMessages(['disbursement' => 'This payment already has a linked reversal.']);
            }
            $this->assertPostingEligible($original, now('Asia/Manila')->toDateString());
            $date = now('Asia/Manila')->toDateString();
            $period = $this->periodFor($date);
            $lines = $original->journal->lines->map(fn ($line) => [
                'accounting_account_id' => $line->accounting_account_id,
                'debit_cents' => (int) $line->credit_cents,
                'credit_cents' => (int) $line->debit_cents,
            ])->all();
            $reversal = CashDisbursement::query()->create([
                'reference' => 'REV-CD-'.$original->id,
                'payee' => $original->payee,
                'payment_date' => $date,
                'method' => $original->method,
                'check_number' => $original->check_number,
                'money_account_id' => $original->money_account_id,
                'description' => 'Reversal of '.$original->reference.': '.$reason,
                'evidence_reference' => $original->evidence_reference,
                'amount_cents' => $original->amount_cents,
                'status' => 'draft',
                'posting_period_id' => $period->id,
                'reversal_of_id' => $original->id,
                'correction_reason' => $reason,
                'prepared_by' => $actorId,
            ]);
            $journal = AccountingJournal::query()->create([
                'book_key' => 'FCDC',
                'reference' => 'CD-REV-'.$original->id,
                'source_type' => 'cash_disbursement_reversal',
                'source_id' => (string) $original->id,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => $reversal->description,
                'external_reference' => $original->reference,
                'correction_of_id' => $original->journal_id,
                'correction_reason' => $reason,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ]);
            $journal->lines()->createMany($lines);
            $journal->forceFill(['status' => 'posted', 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();
            $reversal->forceFill(['status' => 'posted', 'journal_id' => $journal->id, 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();

            return $reversal->load(['journal.lines.account', 'reversalOf']);
        });
    }

    private function validateDraft(array $input): array
    {
        $data = Validator::make($input, [
            'payee' => ['required', 'string', 'max:180'],
            'paymentDate' => ['required', 'date_format:Y-m-d'],
            'method' => ['required', 'string', 'max:40'],
            'moneyAccountId' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'reference' => ['required', 'string', 'max:100'],
            'checkNumber' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:4000'],
            'evidenceReference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.accounting_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'allocations.*.description' => ['required', 'string', 'max:255'],
            'allocations.*.amount' => ['required', 'string'],
        ])->validate();

        if (! in_array($data['method'], ['Cash', 'Bank Transfer', 'Check'], true)) {
            $mapping = AccountingPostingMapping::query()->where('source', 'disbursement_method:'.$data['method'])->with('account')->first();
            if (! $mapping?->isApprovedForPosting() || $mapping->account->type !== 'Asset'
                || ! in_array($mapping->account->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['method' => 'Only explicitly approved additional payment methods are accepted.']);
            }
        }
        if ($data['method'] === 'Check' && trim((string) ($data['checkNumber'] ?? '')) === '') {
            throw ValidationException::withMessages(['checkNumber' => 'A check number is required for checks.']);
        }
        if ($data['method'] !== 'Cash' && trim($data['reference']) === '') {
            throw ValidationException::withMessages(['reference' => 'A payment reference is required for non-cash methods.']);
        }
        $amountCents = $this->cents($data['amount'], 'amount');
        $allocations = [];
        $sum = 0;
        foreach ($data['allocations'] as $index => $line) {
            $cents = $this->cents($line['amount'], "allocations.$index.amount");
            if ($cents <= 0 || $sum > PHP_INT_MAX - $cents) {
                throw ValidationException::withMessages(["allocations.$index.amount" => 'Each debit allocation must be positive and supported.']);
            }
            $sum += $cents;
            $allocations[] = [
                'accounting_account_id' => (int) $line['accounting_account_id'],
                'description' => trim($line['description']),
                'amount_cents' => $cents,
            ];
        }
        if ($amountCents <= 0 || $sum !== $amountCents) {
            throw ValidationException::withMessages(['allocations' => 'Approved debit allocations must exactly equal the payment amount.']);
        }

        return [
            ...$data,
            'payee' => trim($data['payee']),
            'reference' => trim($data['reference']),
            'description' => trim($data['description']),
            'evidenceReference' => trim($data['evidenceReference']),
            'moneyAccountId' => (int) $data['moneyAccountId'],
            'amountCents' => $amountCents,
            'allocations' => $allocations,
        ];
    }

    private function assertPostingEligible(CashDisbursement $disbursement, ?string $postingDate = null): void
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $postingDate ?? $disbursement->payment_date->toDateString(), 'Asia/Manila');
        if ($date->greaterThan(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['paymentDate' => 'Completed payments cannot be future-dated.']);
        }
        $cutover = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        if (! $cutover || $date->lt(CarbonImmutable::parse($cutover, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['paymentDate' => 'Payment date is outside approved accounting cutover coverage.']);
        }
        $period = $this->periodFor($date->toDateString());
        $period = AccountingPostingPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();
        if ($period->status !== 'open' || $date->lt(CarbonImmutable::parse($period->starts_on, 'Asia/Manila'))
            || $date->gt(CarbonImmutable::parse($period->ends_on, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['paymentDate' => 'Payment date is not in an open accounting period.']);
        }
        $moneyAccount = AccountingAccount::query()->lockForUpdate()->find($disbursement->money_account_id);
        if (! $moneyAccount?->isApprovedForPosting() || $moneyAccount->type !== 'Asset'
            || ! in_array($moneyAccount->classification, ['cash', 'bank'], true)) {
            throw ValidationException::withMessages(['moneyAccountId' => 'Select an active, approved Cash or Bank account.']);
        }
        $total = 0;
        if ($disbursement->lines->isEmpty()) {
            throw ValidationException::withMessages(['allocations' => 'At least one debit allocation is required.']);
        }
        foreach ($disbursement->lines as $line) {
            $account = AccountingAccount::query()->lockForUpdate()->find($line->accounting_account_id);
            if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Asset', 'Expense'], true)
                || in_array($account->classification, [
                    'cash', 'bank', 'accounts_receivable', 'card_clearing', 'accounts_payable',
                    'inventory', 'input_vat', 'output_vat', 'cost_of_goods_sold',
                ], true)) {
                throw ValidationException::withMessages(['allocations' => 'Debit allocations require active approved non-control Asset or Expense accounts.']);
            }
            if ($total > PHP_INT_MAX - (int) $line->amount_cents) {
                throw ValidationException::withMessages(['allocations' => 'Allocation total exceeds the supported PHP-centavo range.']);
            }
            $total += (int) $line->amount_cents;
        }
        if ($total !== (int) $disbursement->amount_cents) {
            throw ValidationException::withMessages(['allocations' => 'Debit allocations must exactly equal the payment amount.']);
        }
        if (! in_array($disbursement->method, ['Cash', 'Bank Transfer', 'Check'], true)) {
            $mapping = AccountingPostingMapping::query()->where('source', 'disbursement_method:'.$disbursement->method)
                ->with('account')->lockForUpdate()->first();
            if (! $mapping?->isApprovedForPosting() || $mapping->account->type !== 'Asset'
                || ! in_array($mapping->account->classification, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['method' => 'Only explicitly approved additional payment methods are accepted.']);
            }
        }
    }

    private function periodFor(string $date): AccountingPostingPeriod
    {
        $year = (int) substr($date, 0, 4);

        return AccountingPostingPeriod::query()->firstOrCreate(
            ['book_key' => 'FCDC', 'fiscal_year' => $year],
            ['starts_on' => "$year-01-01", 'ends_on' => "$year-12-31", 'status' => 'open'],
        );
    }

    private function cents(mixed $amount, string $field): int
    {
        $value = trim((string) $amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([$field => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        [$pesos, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($pesos, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) $fraction > PHP_INT_MAX % 100)) {
            throw ValidationException::withMessages([$field => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) $fraction;
    }
}
