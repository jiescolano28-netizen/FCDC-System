<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FiscalYearClosingService
{
    private const INCOME_CLASSIFICATIONS = [
        'sales', 'cost_of_goods_sold', 'operating_expense', 'other_income', 'other_expense',
    ];

    public function close(int $year, int $retainedEarningsAccountId, string $reason, Employee $actor): AccountingJournal
    {
        abort_unless($actor->can('accounting.close-fiscal-year'), 403);
        $reason = trim($reason);
        validator(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:3', 'max:4000'],
        ])->validate();

        return DB::transaction(function () use ($year, $retainedEarningsAccountId, $reason, $actor): AccountingJournal {
            $periods = AccountingPostingPeriod::query()->where('book_key', 'FCDC')
                ->where('fiscal_year', $year)->orderBy('period_month')->lockForUpdate()->get();
            if ($periods->count() !== 12 || $periods->contains(fn (AccountingPostingPeriod $period) => $period->status !== 'closed')) {
                throw ValidationException::withMessages(['fiscalYear' => 'Close all twelve reconciled monthly periods before closing the fiscal year.']);
            }
            $december = $periods->firstWhere('period_month', 12);
            if (! $december || $december->ends_on->toDateString() >= now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['fiscalYear' => 'Only a completed fiscal year can be closed.']);
            }
            $readiness = app(AccountingPeriodService::class)->readiness($december);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages(['fiscalYear' => implode(' ', $readiness['blockers'])]);
            }

            $priorClosings = AccountingJournal::query()->where('book_key', 'FCDC')
                ->where('source_type', 'fiscal_year_closing')
                ->where(fn ($query) => $query->where('source_id', 'like', $year.':%')->orWhereYear('accounting_date', $year))
                ->orderByDesc('id')->lockForUpdate()->get();
            foreach ($priorClosings as $prior) {
                if (! $prior->corrections()->where('status', 'posted')->exists()) {
                    throw ValidationException::withMessages(['fiscalYear' => 'This fiscal year already has an active closing entry.']);
                }
            }

            $retained = AccountingAccount::query()->lockForUpdate()->find($retainedEarningsAccountId);
            if (! $retained?->isApprovedForPosting() || $retained->type !== 'Equity'
                || $retained->classification !== 'retained_earnings') {
                throw ValidationException::withMessages(['retainedEarningsAccountId' => 'Select an active, approved Retained / accumulated earnings account.']);
            }
            $cutover = AccountingJournal::query()->where('book_key', 'FCDC')->where('source_type', 'opening')
                ->where('source_id', 'FCDC')->where('status', 'posted')->value('accounting_date');
            if (! $cutover) {
                throw ValidationException::withMessages(['fiscalYear' => 'Approved accounting cutover is required before fiscal closing.']);
            }

            $incomeAccounts = AccountingAccount::query()->whereIn('type', ['Revenue', 'Expense'])
                ->whereIn('classification', self::INCOME_CLASSIFICATIONS)->whereNotNull('approved_at')->get();
            $postedAccountIds = AccountingJournalLine::query()->whereHas('journal', fn ($query) => $query
                ->where('book_key', 'FCDC')->where('status', 'posted')->whereDate('accounting_date', '<=', $december->ends_on))
                ->pluck('accounting_account_id')->unique();
            $unsupported = AccountingAccount::query()->whereIn('id', $postedAccountIds)
                ->whereIn('type', ['Revenue', 'Expense'])->where(fn ($query) => $query
                ->whereNull('approved_at')->orWhereNotIn('classification', self::INCOME_CLASSIFICATIONS))->exists();
            if ($unsupported) {
                throw ValidationException::withMessages(['fiscalYear' => 'Every posted income and expense account must have an approved supported classification.']);
            }

            $summary = null;
            if (substr((string) $cutover, 0, 4) === (string) $year && substr((string) $cutover, 5, 2) !== '01') {
                $summary = AccountingYtdSummary::query()->where('book_key', 'FCDC')
                    ->where('fiscal_year', $year)->where('status', 'approved')->with('lines.account')->first();
                $priorDay = CarbonImmutable::parse($cutover, 'Asia/Manila')->subDay()->toDateString();
                if (! $summary || $summary->through_date->toDateString() !== $priorDay) {
                    throw ValidationException::withMessages(['fiscalYear' => 'Approved pre-cutover YTD summary coverage is required for fiscal closing.']);
                }
            }

            $priorCloseIds = $priorClosings->modelKeys();
            $excluded = $priorCloseIds;
            $frontier = $priorCloseIds;
            while ($frontier !== []) {
                $next = AccountingJournal::query()->where('book_key', 'FCDC')->whereIn('correction_of_id', $frontier)->pluck('id')->all();
                $frontier = array_values(array_diff($next, $excluded));
                $excluded = [...$excluded, ...$frontier];
            }
            $balances = AccountingJournalLine::query()->with('account')
                ->whereIn('accounting_account_id', $incomeAccounts->modelKeys())
                ->whereHas('journal', function ($query) use ($year, $cutover, $excluded): void {
                    $query->where('book_key', 'FCDC')->where('status', 'posted')
                        ->whereDate('accounting_date', '>=', max($year.'-01-01', substr((string) $cutover, 0, 10)))
                        ->whereDate('accounting_date', '<=', $year.'-12-31')
                        ->where('source_type', '!=', 'opening');
                    if ($excluded !== []) {
                        $query->whereNotIn('id', $excluded);
                    }
                })->get()->groupBy('accounting_account_id');

            $lines = [];
            $netIncome = 0;
            foreach ($incomeAccounts as $account) {
                $netDebit = (int) $balances->get($account->id, collect())->sum(
                    fn (AccountingJournalLine $line) => (int) $line->debit_cents - (int) $line->credit_cents,
                );
                $amount = $account->type === 'Revenue' ? -$netDebit : $netDebit;
                if ($summary) {
                    $summaryLine = $summary->lines->first(fn ($line) => $line->accounting_account_id === $account->id);
                    if ($summaryLine) {
                        $expected = $account->type === 'Revenue' ? 'credit' : 'debit';
                        $amount += (int) $summaryLine->amount_cents * ($account->normal_balance === $expected ? 1 : -1);
                    }
                }
                $netIncome += $account->type === 'Revenue' ? $amount : -$amount;
                if ($amount === 0) {
                    continue;
                }
                $isCreditNormal = $account->type === 'Revenue';
                $debit = $isCreditNormal ? max(0, $amount) : max(0, -$amount);
                $credit = $isCreditNormal ? max(0, -$amount) : max(0, $amount);
                if ($debit > 0 || $credit > 0) {
                    $lines[] = [
                        'accounting_account_id' => $account->id, 'description' => 'Close '.$account->name,
                        'debit_cents' => $debit, 'credit_cents' => $credit,
                    ];
                }
            }
            if ($netIncome !== 0) {
                $lines[] = [
                    'accounting_account_id' => $retained->id, 'description' => 'Transfer fiscal-year net income to retained earnings',
                    'debit_cents' => $netIncome < 0 ? -$netIncome : 0,
                    'credit_cents' => $netIncome > 0 ? $netIncome : 0,
                ];
            }
            $debits = (int) collect($lines)->sum('debit_cents');
            $credits = (int) collect($lines)->sum('credit_cents');
            if ($debits <= 0 || $debits !== $credits) {
                throw ValidationException::withMessages(['fiscalYear' => 'Fiscal closing amounts do not balance; correct income account coverage before closing.']);
            }

            $sequence = (int) $priorClosings->max(fn (AccountingJournal $journal) => (int) substr((string) $journal->source_id, strlen((string) $year) + 1)) + 1;
            $reference = sprintf('FY-CLOSE-%d-R%d', $year, $sequence);
            $journal = AccountingJournal::create([
                'book_key' => 'FCDC', 'reference' => $reference, 'source_type' => 'fiscal_year_closing',
                'source_id' => $year.':'.$sequence, 'accounting_date' => $year.'-12-31',
                'posting_period_id' => $december->id, 'description' => $reason,
                'status' => 'draft', 'prepared_by' => $actor->id,
            ]);
            $journal->lines()->createMany($lines);
            $journal->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actor->id])->save();
            foreach ($incomeAccounts->merge([$retained]) as $account) {
                if (collect($lines)->contains(fn (array $line) => $line['accounting_account_id'] === $account->id)) {
                    $account->markUsed();
                }
            }

            return $journal->load('lines.account');
        });
    }

    public function reverseClosingsForReopen(iterable $periods, string $reason, Employee $actor): void
    {
        $years = collect($periods)->pluck('fiscal_year')->unique();
        foreach ($years as $year) {
            $closings = AccountingJournal::query()->where('book_key', 'FCDC')->where('source_type', 'fiscal_year_closing')
                ->where(fn ($query) => $query->where('source_id', 'like', $year.':%')->orWhereYear('accounting_date', $year))
                ->where('status', 'posted')->with('lines')
                ->lockForUpdate()->get();
            foreach ($closings as $closing) {
                if ($closing->corrections()->where('status', 'posted')->exists()) {
                    continue;
                }
                $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($closing->posting_period_id);
                $date = $closing->accounting_date->toDateString();
                $reference = 'FY-REV-'.$closing->id.'-'.Str::upper(Str::random(8));
                $reversal = AccountingJournal::create([
                    'book_key' => 'FCDC', 'reference' => $reference, 'source_type' => 'reversal',
                    'source_id' => 'fiscal-close-reversal:'.$closing->id, 'accounting_date' => $date,
                    'posting_period_id' => $period->id, 'description' => 'Reversal of '.$closing->reference.': '.$reason,
                    'correction_of_id' => $closing->id, 'correction_reason' => $reason,
                    'status' => 'draft', 'prepared_by' => $actor->id,
                ]);
                $reversal->lines()->createMany($closing->lines->map(fn (AccountingJournalLine $line) => [
                    'accounting_account_id' => $line->accounting_account_id,
                    'description' => 'Reverse '.$closing->reference,
                    'debit_cents' => $line->credit_cents,
                    'credit_cents' => $line->debit_cents,
                ])->all());
                $reversal->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $actor->id])->save();
            }
        }
    }
}
