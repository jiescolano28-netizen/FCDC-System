<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Models\SupplierOpeningInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBooksService
{
    public function saveOpening(string $date, array $lines, int $actorId): AccountingJournal
    {
        $date = $this->validBusinessDate($date);
        $validatedLines = $this->validatedOpeningLines($lines);
        $year = (int) substr($date, 0, 4);

        return DB::transaction(function () use ($date, $validatedLines, $actorId, $year): AccountingJournal {
            $period = AccountingPostingPeriod::firstOrCreate(
                ['book_key' => 'FCDC', 'fiscal_year' => $year],
                ['starts_on' => "$year-01-01", 'ends_on' => "$year-12-31", 'status' => 'open'],
            );
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->lockForUpdate()
                ->first();
            if ($journal && $journal->status !== 'draft') {
                throw ValidationException::withMessages(['opening' => 'An approved opening journal already exists.']);
            }
            $journal ??= new AccountingJournal(['source_type' => 'opening', 'source_id' => 'FCDC']);
            $journal->fill([
                'book_key' => 'FCDC',
                'reference' => 'OPENING-FCDC-'.$year,
                'accounting_date' => $date,
                'posting_period_id' => $period->id,
                'description' => 'Approved opening balances at accounting cutover',
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $journal->lines()->delete();
            foreach ($validatedLines as $line) {
                $journal->lines()->create($line);
            }

            return $journal->load('lines.account');
        });
    }

    public function approveOpening(int $actorId): AccountingJournal
    {
        return DB::transaction(function () use ($actorId): AccountingJournal {
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->lockForUpdate()
                ->with('lines.account')
                ->firstOrFail();
            if ($journal->status !== 'draft') {
                throw ValidationException::withMessages(['opening' => 'This opening journal is already approved and immutable.']);
            }
            $this->assertBalancedLines($journal->lines->map(fn ($line) => [
                'accountId' => $line->accounting_account_id,
                'debitCents' => $line->debit_cents,
                'creditCents' => $line->credit_cents,
            ])->all());

            foreach ($journal->lines as $line) {
                $account = $line->account;
                if (! $account?->isApprovedForPosting()) {
                    throw ValidationException::withMessages(['opening' => 'Every opening account must be active and approved.']);
                }
                if ($account->classification === 'inventory') {
                    throw ValidationException::withMessages([
                        'opening' => 'Inventory opening requires an approved per-item valuation schedule, which is not available.',
                    ]);
                }
            }

            app(SupplierPayablesService::class)->postOpeningSchedule($journal, $actorId);

            $journal->forceFill([
                'status' => 'posted',
                'posted_at' => now('UTC'),
                'posted_by' => $actorId,
                'approved_at' => now('UTC'),
                'approved_by' => $actorId,
            ])->save();
            foreach ($journal->lines as $line) {
                $line->account->markUsed();
            }

            return $journal->refresh()->load('lines.account');
        });
    }

    public function saveYtdSummary(int $year, string $throughDate, string $evidence, array $lines, int $actorId): AccountingYtdSummary
    {
        $throughDate = $this->validBusinessDate($throughDate);
        if ((int) substr($throughDate, 0, 4) !== $year || trim($evidence) === '') {
            throw ValidationException::withMessages(['ytdLines' => 'YTD evidence must identify a date and supporting schedule in the cutover fiscal year.']);
        }
        $amounts = $this->validatedYtdLines($lines);

        return DB::transaction(function () use ($year, $throughDate, $evidence, $amounts, $actorId): AccountingYtdSummary {
            $summary = AccountingYtdSummary::firstOrNew(['book_key' => 'FCDC', 'fiscal_year' => $year]);
            if ($summary->exists && $summary->status !== 'draft') {
                throw ValidationException::withMessages(['ytdLines' => 'An approved YTD summary is immutable.']);
            }
            $summary->fill([
                'through_date' => $throughDate,
                'evidence_reference' => trim($evidence),
                'status' => 'draft',
                'prepared_by' => $actorId,
            ])->save();
            $summary->lines()->delete();
            foreach ($amounts as $line) {
                $summary->lines()->create($line);
            }

            return $summary->load('lines.account');
        });
    }

    public function approveYtdSummary(int $actorId): AccountingYtdSummary
    {
        return DB::transaction(function () use ($actorId): AccountingYtdSummary {
            $journal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->where('status', 'posted')
                ->firstOrFail();
            $cutover = CarbonImmutable::parse($journal->accounting_date, 'Asia/Manila');
            $summary = AccountingYtdSummary::where('book_key', 'FCDC')
                ->where('fiscal_year', $cutover->year)
                ->lockForUpdate()
                ->with('lines.account')
                ->firstOrFail();
            $through = CarbonImmutable::parse($summary->through_date, 'Asia/Manila');
            if ($summary->status !== 'draft' || $cutover->toDateString() === $cutover->startOfYear()->toDateString()
                || $through->toDateString() !== $cutover->subDay()->toDateString()) {
                throw ValidationException::withMessages(['ytdLines' => 'A non-January 1 cutover requires an unapproved YTD summary through the day before cutover.']);
            }
            if ($summary->lines->isEmpty() || $summary->evidence_reference === '') {
                throw ValidationException::withMessages(['ytdLines' => 'YTD approval requires non-empty supported income/expense balances.']);
            }
            foreach ($summary->lines as $line) {
                if (! $line->account?->isApprovedForPosting() || ! in_array($line->account->type, ['Revenue', 'Expense'], true)) {
                    throw ValidationException::withMessages(['ytdLines' => 'YTD summaries may use only active approved income and expense accounts.']);
                }
                $line->account->markUsed();
            }
            $summary->forceFill(['status' => 'approved', 'approved_at' => now('UTC'), 'approved_by' => $actorId])->save();

            return $summary->refresh()->load('lines.account');
        });
    }

    public function readiness(): array
    {
        $openingJournal = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->first();
        $journal = $openingJournal?->status === 'posted' ? $openingJournal : null;
        $cutoverDate = $openingJournal?->accounting_date?->toDateString();
        $year = $cutoverDate ? (int) substr($cutoverDate, 0, 4) : now('Asia/Manila')->year;
        $midyear = $cutoverDate !== null && $cutoverDate !== "$year-01-01";
        $chartApproved = AccountingAccount::where('is_active', true)->exists()
            && ! AccountingAccount::where('is_active', true)->whereNull('approved_at')->exists();
        $requiredMappings = [...array_keys(AccountingPostingMapping::REQUIRED_CLASSIFICATIONS), 'recovery_offset', 'adjustment'];
        $mappings = AccountingPostingMapping::whereIn('source', $requiredMappings)->with('account')->get()->keyBy('source');
        $mappingsApproved = collect($requiredMappings)->every(fn (string $source) => $mappings->get($source)?->isApprovedForPosting() === true);
        $ytdApproved = AccountingYtdSummary::where('book_key', 'FCDC')->where('fiscal_year', $year)->where('status', 'approved')->exists();
        $postedSupplierInvoices = SupplierOpeningInvoice::where('status', 'posted')->get();
        $apLines = $journal ? $journal->lines()->with('account')->get()->filter(
            fn ($line) => $line->account?->classification === 'accounts_payable',
        ) : collect();
        $apScheduleMatches = $apLines->sum('credit_cents') === $postedSupplierInvoices->sum('amount_cents')
            && $apLines->sum('debit_cents') === 0;

        return [
            'cutover' => $journal ? 'Approved' : 'Cutover approval required',
            'chart' => $chartApproved ? 'Approved chart available' : 'Chart approval required',
            'mappings' => $mappingsApproved ? 'Posting mappings approved' : 'Posting mapping approvals required',
            'opening' => $journal ? 'Balanced opening journal approved' : 'Opening journal approval required',
            'supplier' => $journal
                ? ($apScheduleMatches ? 'Opening supplier schedule reconciled' : 'Opening supplier schedule mismatch')
                : (SupplierOpeningInvoice::where('status', 'draft')->exists()
                    ? 'Opening supplier schedule pending approval'
                    : 'Opening supplier schedule required'),
            'inventory' => 'Inventory valuation schedule unavailable',
            'valuation' => 'Valuation policies not approved',
            'ytd' => $cutoverDate === null
                ? 'Save a cutover date to determine YTD evidence requirements'
                : (! $midyear
                    ? 'Not required for January 1 cutover'
                    : ($ytdApproved ? 'Approved pre-cutover YTD evidence' : 'Pre-cutover YTD evidence required')),
            'production' => 'Production activation unavailable: inventory valuation schedule and valuation policies remain outstanding; chart, mapping, cutover and supplier readiness are listed above.',
        ];
    }

    private function validatedOpeningLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Opening journal requires at least two non-empty lines.']);
        }
        $result = [];
        foreach ($lines as $index => $line) {
            $accountId = filter_var($line['accountId'] ?? null, FILTER_VALIDATE_INT);
            $account = $accountId ? AccountingAccount::find($accountId) : null;
            if (! $account?->isApprovedForPosting()) {
                throw ValidationException::withMessages(["lines.$index.accountId" => 'Select an active approved account.']);
            }
            if ($account->classification === 'inventory') {
                throw ValidationException::withMessages(["lines.$index.accountId" => 'Controlled Inventory openings require an approved per-item valuation schedule.']);
            }
            $debit = $this->amountInCents($line['debit'] ?? '');
            $credit = $this->amountInCents($line['credit'] ?? '');
            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages(["lines.$index" => 'Each opening line must contain exactly one positive debit or credit amount.']);
            }
            $result[] = ['accounting_account_id' => $account->id, 'debit_cents' => $debit, 'credit_cents' => $credit];
        }
        $this->assertBalancedLines(array_map(fn ($line) => [
            'accountId' => $line['accounting_account_id'],
            'debitCents' => $line['debit_cents'],
            'creditCents' => $line['credit_cents'],
        ], $result));

        return $result;
    }

    private function validatedYtdLines(array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['ytdLines' => 'YTD summary requires supported income or expense balances.']);
        }
        $result = [];
        foreach ($lines as $index => $line) {
            $accountId = filter_var($line['accountId'] ?? null, FILTER_VALIDATE_INT);
            $account = $accountId ? AccountingAccount::find($accountId) : null;
            if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Revenue', 'Expense'], true)) {
                throw ValidationException::withMessages(["ytdLines.$index.accountId" => 'Select an active approved Revenue or Expense account.']);
            }
            $amount = $this->amountInCents($line['amount'] ?? '');
            if ($amount <= 0) {
                throw ValidationException::withMessages(["ytdLines.$index.amount" => 'YTD amounts must be positive PHP centavos.']);
            }
            $result[] = ['accounting_account_id' => $account->id, 'amount_cents' => $amount];
        }

        return $result;
    }

    private function assertBalancedLines(array $lines): void
    {
        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            $debit = (int) $line['debitCents'];
            $credit = (int) $line['creditCents'];
            if ($debit > PHP_INT_MAX - $debits || $credit > PHP_INT_MAX - $credits) {
                throw ValidationException::withMessages(['lines' => 'Opening journal total exceeds the supported PHP centavo range.']);
            }
            $debits += $debit;
            $credits += $credit;
        }
        if (count($lines) < 2 || $debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages(['lines' => 'Opening journal must have positive, exactly equal debit and credit totals.']);
        }
    }

    private function amountInCents(mixed $amount): int
    {
        if ($amount === '' || $amount === null) {
            return 0;
        }
        if (! is_string($amount) && ! is_int($amount)) {
            throw ValidationException::withMessages(['lines' => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        $amount = trim((string) $amount);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.(\d{1,2}))?$/D', $amount)) {
            throw ValidationException::withMessages(['lines' => 'Amounts must be non-negative PHP values with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $cents = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        $maximumCents = (string) PHP_INT_MAX;
        if (strlen($cents) > strlen($maximumCents)
            || (strlen($cents) === strlen($maximumCents) && strcmp($cents, $maximumCents) > 0)) {
            throw ValidationException::withMessages(['lines' => 'Amount exceeds the supported PHP centavo range.']);
        }

        return (int) $cents;
    }

    private function validBusinessDate(string $date): string
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (\Throwable) {
            $parsed = false;
        }
        if (! $parsed || $parsed->format('Y-m-d') !== $date || $parsed->isFuture()) {
            throw ValidationException::withMessages(['cutoverDate' => 'Cutover date must be a valid, non-future Manila business date.']);
        }

        return $parsed->toDateString();
    }
}
