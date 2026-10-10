<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualJournalService
{
    public function saveDraft(array $data, int $actorId, ?int $journalId = null): AccountingJournal
    {
        $validated = $this->validatedDraft($data, $journalId);

        return DB::transaction(function () use ($validated, $actorId, $journalId): AccountingJournal {
            $journal = $journalId
                ? AccountingJournal::where('status', 'draft')->lockForUpdate()->findOrFail($journalId)
                : new AccountingJournal;
            if ($journal->exists && $journal->source_type !== 'manual') {
                throw ValidationException::withMessages(['journal' => 'Only manual drafts can be edited.']);
            }
            $period = $this->periodFor($validated['accountingDate']);
            $journal->fill([
                'book_key' => 'FCDC',
                'reference' => $validated['reference'],
                'source_type' => 'manual',
                'source_id' => $validated['reference'],
                'accounting_date' => $validated['accountingDate'],
                'posting_period_id' => $period->id,
                'description' => $validated['description'],
                'external_reference' => $validated['externalReference'] ?: null,
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $journal->lines()->delete();
            foreach ($validated['lines'] as $line) {
                $journal->lines()->create($line);
            }

            return $journal->load('lines.account');
        });
    }

    public function deleteDraft(int $journalId): void
    {
        DB::transaction(function () use ($journalId): void {
            $journal = AccountingJournal::where('source_type', 'manual')->where('status', 'draft')
                ->lockForUpdate()->findOrFail($journalId);
            $journal->lines()->delete();
            $journal->delete();
        });
    }

    public function postDraft(int $journalId, int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($journalId, $actorId): AccountingJournal {
            $journal = AccountingJournal::where('source_type', 'manual')->where('status', 'draft')
                ->lockForUpdate()->with('lines.account')->findOrFail($journalId);
            $this->assertPostingEligible($journal->accounting_date->toDateString(), $journal->lines);
            $journal->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actorId])->save();
            foreach ($journal->lines as $line) {
                $line->account->markUsed();
            }

            return $journal->refresh()->load('lines.account');
        });
    }

    public function correct(int $journalId, array $data, int $actorId): array
    {
        $validated = $this->validatedDraft($data);
        $validated['accountingDate'] = now('Asia/Manila')->toDateString();
        $correction = validator($data, [
            'correctionReason' => ['required', 'string', 'max:1000'],
            'correctionReference' => ['required', 'string', 'max:80'],
        ])->validate();
        $reason = trim($correction['correctionReason']);
        $reversalReference = trim($correction['correctionReference']);
        if ($reversalReference === $validated['reference']) {
            throw ValidationException::withMessages(['correctionReference' => 'Reversal and replacement references must be distinct.']);
        }
        if (AccountingJournal::where('reference', $reversalReference)->exists()) {
            throw ValidationException::withMessages(['correctionReference' => 'Journal reference is already in use.']);
        }

        return DB::transaction(function () use ($journalId, $validated, $reason, $reversalReference, $actorId): array {
            $original = AccountingJournal::where('status', 'posted')->whereIn('source_type', ['manual', 'replacement'])
                ->whereDoesntHave('corrections')
                ->lockForUpdate()->with('lines.account')->findOrFail($journalId);
            $this->assertPostingEligible($validated['accountingDate'], $original->lines);
            $replacementLines = collect($validated['lines'])->map(fn (array $line) => (object) [
                'accounting_account_id' => $line['accounting_account_id'],
                'debit_cents' => $line['debit_cents'],
                'credit_cents' => $line['credit_cents'],
                'account' => AccountingAccount::find($line['accounting_account_id']),
            ]);
            $this->assertPostingEligible($validated['accountingDate'], $replacementLines);
            $period = $this->periodFor($validated['accountingDate']);
            $reversal = $this->createPostedEntry(
                $reversalReference,
                'reversal',
                $validated['accountingDate'],
                $period,
                'Reversal of '.$original->reference.': '.$reason,
                $reason,
                $original->id,
                $actorId,
                $original->lines->map(fn (AccountingJournalLine $line) => [
                    'accounting_account_id' => $line->accounting_account_id,
                    'description' => $line->description,
                    'debit_cents' => $line->credit_cents,
                    'credit_cents' => $line->debit_cents,
                ])->all(),
            );
            $replacement = $this->createPostedEntry(
                $validated['reference'],
                'replacement',
                $validated['accountingDate'],
                $period,
                $validated['description'],
                $reason,
                $original->id,
                $actorId,
                $validated['lines'],
                $validated['externalReference'] ?: null,
            );
            foreach ($reversal->lines->merge($replacement->lines) as $line) {
                $line->account->markUsed();
            }

            return [$reversal, $replacement];
        });
    }

    private function createPostedEntry(
        string $reference,
        string $sourceType,
        string $date,
        AccountingPostingPeriod $period,
        string $description,
        string $reason,
        int $originalId,
        int $actorId,
        array $lines,
        ?string $externalReference = null,
    ): AccountingJournal {
        $journal = AccountingJournal::create([
            'book_key' => 'FCDC',
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $reference,
            'accounting_date' => $date,
            'posting_period_id' => $period->id,
            'description' => $description,
            'external_reference' => $externalReference,
            'correction_of_id' => $originalId,
            'correction_reason' => $reason,
            'status' => 'draft',
            'prepared_by' => $actorId,
        ]);
        $journal->lines()->createMany($lines);
        $journal->forceFill(['status' => 'posted', 'posted_by' => $actorId, 'posted_at' => now('UTC')])->save();

        return $journal->load('lines.account');
    }

    private function validatedDraft(array $data, ?int $journalId = null): array
    {
        validator($data, [
            'accountingDate' => ['required', 'date_format:Y-m-d'],
            'reference' => ['required', 'string', 'max:80'],
            'externalReference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.accountId' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'string', 'max:30'],
            'lines.*.credit' => ['nullable', 'string', 'max:30'],
        ])->validate();
        if (AccountingJournal::where('reference', trim($data['reference']))
            ->when($journalId !== null, fn ($query) => $query->whereKeyNot($journalId))->exists()) {
            throw ValidationException::withMessages(['reference' => 'Journal reference is already in use.']);
        }

        $lines = [];
        foreach ($data['lines'] as $index => $line) {
            $debit = $this->cents($line['debit'] ?? '');
            $credit = $this->cents($line['credit'] ?? '');
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.$index" => 'Each line must have exactly one positive debit or credit in centavos.']);
            }
            $lines[] = [
                'accounting_account_id' => (int) $line['accountId'],
                'description' => trim((string) ($line['description'] ?? '')) ?: null,
                'debit_cents' => $debit,
                'credit_cents' => $credit,
            ];
        }

        return [
            'accountingDate' => $data['accountingDate'],
            'reference' => trim($data['reference']),
            'externalReference' => trim((string) ($data['externalReference'] ?? '')),
            'description' => trim($data['description']),
            'lines' => $lines,
        ];
    }

    private function assertPostingEligible(string $date, iterable $lines): void
    {
        $dateValue = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        if (! $dateValue || $dateValue->greaterThan(CarbonImmutable::now('Asia/Manila')->startOfDay())) {
            throw ValidationException::withMessages(['journal' => 'Completed accounting events cannot be future-dated.']);
        }
        $cutover = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        $cutoverDate = $cutover ? CarbonImmutable::parse($cutover)->toDateString() : null;
        $cutoverValue = $cutoverDate
            ? CarbonImmutable::createFromFormat('!Y-m-d', $cutoverDate, 'Asia/Manila')
            : null;
        if (! $cutoverValue || $dateValue->lt($cutoverValue)) {
            throw ValidationException::withMessages(['journal' => 'Posting date is outside approved accounting cutover coverage.']);
        }
        $period = app(AccountingPeriodService::class)->lockOpenPeriodForDate(
            $date,
            'journal',
            'The accounting date is not in an open accounting period.',
        );
        if ($dateValue->lt(CarbonImmutable::parse($period->starts_on, 'Asia/Manila'))
            || $dateValue->gt(CarbonImmutable::parse($period->ends_on, 'Asia/Manila'))) {
            throw ValidationException::withMessages(['journal' => 'The accounting date is not in an open accounting period.']);
        }

        $debits = 0;
        $credits = 0;
        $count = 0;
        foreach ($lines as $line) {
            $account = $line->account ?? AccountingAccount::find($line->accounting_account_id);
            if (! $account?->isApprovedForPosting()) {
                throw ValidationException::withMessages(['journal' => 'Every journal account must be active and approved for posting.']);
            }
            if (in_array($account->classification, ['accounts_payable', 'inventory'], true)) {
                throw ValidationException::withMessages(['journal' => 'Accounts Payable and Inventory require their controlled supporting schedules.']);
            }
            $debit = (int) $line->debit_cents;
            $credit = (int) $line->credit_cents;
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(['journal' => 'Each line must contain exactly one positive debit or credit.']);
            }
            if ($debits > PHP_INT_MAX - $debit || $credits > PHP_INT_MAX - $credit) {
                throw ValidationException::withMessages(['journal' => 'Journal totals exceed the supported PHP-centavo range.']);
            }
            $debits += $debit;
            $credits += $credit;
            $count++;
        }
        if ($count < 2 || $debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages(['journal' => 'Journal requires at least two lines and equal positive debit and credit totals.']);
        }
    }

    private function periodFor(string $date): AccountingPostingPeriod
    {
        return AccountingPostingPeriod::query()->lockForUpdate()->findOrFail(
            AccountingPostingPeriod::firstOrCreateForDate($date)->id,
        );
    }

    private function cents(mixed $amount): int
    {
        $value = trim((string) $amount);
        if ($value === '') {
            return 0;
        }
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['lines' => 'Amounts must be non-negative PHP values with no more than two decimal places.']);
        }
        [$pesos, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($pesos, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        $fraction = str_pad($fraction, 2, '0');
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) $fraction > PHP_INT_MAX % 100)) {
            throw ValidationException::withMessages(['lines' => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) $fraction;
    }
}
