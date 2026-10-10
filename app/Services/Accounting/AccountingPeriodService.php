<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingPeriod;
use App\Models\AccountingYtdSummary;
use App\Models\Employee;
use App\Support\ActivityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    private const BALANCE_CLASSIFICATIONS = [
        'cash', 'bank', 'accounts_receivable', 'card_clearing', 'inventory', 'equipment',
        'other_current_asset', 'other_noncurrent_asset', 'accounts_payable', 'output_vat',
        'other_current_liability', 'other_noncurrent_liability', 'input_vat', 'capital',
        'retained_earnings', 'other_equity',
    ];

    private const INCOME_CLASSIFICATIONS = [
        'sales', 'cost_of_goods_sold', 'operating_expense', 'other_income', 'other_expense',
    ];

    public function readiness(AccountingPostingPeriod $period): array
    {
        $blockers = [];
        $cutoverDate = AccountingJournal::query()->where('book_key', $period->book_key)
            ->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')
            ->value('accounting_date');
        if (! $cutoverDate) {
            $blockers[] = 'Approved accounting cutover is not posted.';
        } else {
            $cutoverDate = substr((string) $cutoverDate, 0, 10);
            if ($period->ends_on->toDateString() < $cutoverDate) {
                $blockers[] = 'The accounting month precedes approved cutover coverage.';
            }
        }

        $lines = AccountingJournalLine::query()
            ->whereHas('journal', fn ($query) => $query->where('book_key', $period->book_key)
                ->where('status', 'posted')->whereDate('accounting_date', '<=', $period->ends_on))
            ->with(['account', 'journal'])->get();
        $debits = (int) $lines->sum('debit_cents');
        $credits = (int) $lines->sum('credit_cents');
        if ($debits !== $credits) {
            $blockers[] = 'Posted books are not exactly balanced through month end.';
        }

        $apAccountIds = AccountingAccount::query()->where('classification', 'accounts_payable')->pluck('id');
        $inventoryAccountIds = AccountingAccount::query()->where('classification', 'inventory')->pluck('id');
        $apBookCents = (int) $lines->whereIn('accounting_account_id', $apAccountIds)
            ->sum(fn ($line) => (int) $line->credit_cents - (int) $line->debit_cents);
        if ($apAccountIds->isNotEmpty()) {
            $apScheduleCents = app(AccountingPositionSchedules::class)->accountsPayableAsOf($period->ends_on->toDateString());
            if ($apBookCents !== $apScheduleCents) {
                $blockers[] = 'Accounts Payable does not match the supplier schedule.';
            }
        }

        $inventoryBookCents = (int) $lines->whereIn('accounting_account_id', $inventoryAccountIds)
            ->sum(fn ($line) => (int) $line->debit_cents - (int) $line->credit_cents);
        if ($inventoryAccountIds->isNotEmpty()) {
            $cutoverDate = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')
                ->where('status', 'posted')->value('accounting_date');
            [$inventoryScheduleCents, $inventoryError] = app(AccountingPositionSchedules::class)
                ->inventoryValueAsOf($period->ends_on->toDateString(), $cutoverDate ? substr((string) $cutoverDate, 0, 10) : null);
            if ($inventoryError || $inventoryScheduleCents === null || $inventoryBookCents !== $inventoryScheduleCents) {
                $blockers[] = $inventoryError ?? 'Inventory does not match the valued-stock schedule.';
            }
        }

        $postedAccountIds = $lines->pluck('accounting_account_id')->unique();
        $statementAccounts = AccountingAccount::query()->whereIn('type', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'])->get();
        $supportedAccounts = $statementAccounts->filter(function (AccountingAccount $account) use ($postedAccountIds, &$blockers): bool {
            $classifications = in_array($account->type, ['Asset', 'Liability', 'Equity'], true)
                ? self::BALANCE_CLASSIFICATIONS
                : self::INCOME_CLASSIFICATIONS;
            $relevant = $account->is_active || $account->approved_at !== null || $postedAccountIds->contains($account->id);
            if ($relevant && ($account->approved_at === null || ! in_array($account->classification, $classifications, true))) {
                $blockers[] = 'Relevant financial statement accounts are unapproved or have unsupported classifications.';
            }

            return $account->approved_at !== null && in_array($account->classification, $classifications, true);
        });
        $statementLines = $lines->whereIn('accounting_account_id', $supportedAccounts->modelKeys());
        if ($supportedAccounts->whereIn('type', ['Asset', 'Liability', 'Equity'])->isEmpty()) {
            $blockers[] = 'Approved Assets, Liabilities and Equity classifications are unavailable.';
        }
        $assets = 0;
        $liabilitiesAndEquity = 0;
        foreach ($statementLines as $line) {
            $account = $line->account;
            if (! $account) {
                continue;
            }
            $netDebit = (int) $line->debit_cents - (int) $line->credit_cents;
            if ($account->type === 'Asset') {
                $assets += $netDebit;
            } elseif (in_array($account->type, ['Liability', 'Equity'], true)) {
                $liabilitiesAndEquity -= $netDebit;
            } elseif (in_array($account->type, ['Revenue', 'Expense'], true)
                && $line->journal?->source_type !== 'opening') {
                $liabilitiesAndEquity -= $netDebit;
            }
        }
        if ($cutoverDate && substr($cutoverDate, 0, 4) === (string) $period->fiscal_year
            && substr($cutoverDate, 5, 2) !== '01') {
            $summary = AccountingYtdSummary::query()->where('book_key', $period->book_key)
                ->where('fiscal_year', $period->fiscal_year)->where('status', 'approved')
                ->with('lines.account')->first();
            $priorDay = CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->subDay()->toDateString();
            if (! $summary || $summary->through_date->toDateString() !== $priorDay) {
                $blockers[] = 'Approved pre-cutover YTD summary coverage is required for close readiness.';
            } else {
                foreach ($summary->lines as $line) {
                    $account = $line->account;
                    if (! $account?->isApprovedForPosting() || ! in_array($account->type, ['Revenue', 'Expense'], true)) {
                        continue;
                    }
                    $expectedNormalBalance = $account->type === 'Revenue' ? 'credit' : 'debit';
                    $balance = (int) $line->amount_cents * ($account->normal_balance === $expectedNormalBalance ? 1 : -1);
                    $liabilitiesAndEquity += $account->type === 'Revenue' ? $balance : -$balance;
                }
            }
        }
        if ($assets !== $liabilitiesAndEquity) {
            $blockers[] = 'Assets do not equal Liabilities plus Equity, including unclosed earnings.';
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'debits_cents' => $debits,
            'credits_cents' => $credits,
            'assets_cents' => $assets,
            'liabilities_and_equity_cents' => $liabilitiesAndEquity,
        ];
    }

    public function lockOpenPeriodForDate(string $date, string $errorKey, string $message): AccountingPostingPeriod
    {
        $period = AccountingPostingPeriod::query()->whereKey(
            AccountingPostingPeriod::firstOrCreateForDate($date)->id,
        )->lockForUpdate()->firstOrFail();
        if ($period->status !== 'open') {
            throw ValidationException::withMessages([$errorKey => $message]);
        }

        return $period;
    }

    public function close(int $periodId, string $reason, Employee $actor): AccountingPostingPeriod
    {
        abort_unless($actor->can('accounting.close-period'), 403);
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:4000']])->validate();

        return DB::transaction(function () use ($periodId, $reason, $actor): AccountingPostingPeriod {
            $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($periodId);
            if ($period->status !== 'open' || $period->ends_on->toDateString() >= now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['period' => 'Only a completed open Manila month can be closed.']);
            }
            $earlierReopened = AccountingPostingPeriod::query()->where('book_key', $period->book_key)
                ->where('status', 'open')->whereNotNull('reopened_at')
                ->where('starts_on', '<', $period->starts_on)->exists();
            if ($earlierReopened) {
                throw ValidationException::withMessages(['period' => 'Reclose reopened months in chronological order.']);
            }
            $readiness = $this->readiness($period);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages(['period' => implode(' ', $readiness['blockers'])]);
            }

            $period->forceFill([
                'status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id,
                'close_reason' => $reason,
            ])->save();
            ActivityAudit::recordChange($period, 'Accounting month closed', [
                'status' => 'closed', 'closed_by' => $actor->id, 'close_reason' => $reason,
            ], ['status' => 'open']);

            return $period->refresh();
        });
    }

    public function reopen(int $periodId, array $dependentPeriodIds, string $reason, Employee $actor): Collection
    {
        abort_unless($actor->can('accounting.reopen-period'), 403);
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:4000']])->validate();

        return DB::transaction(function () use ($periodId, $dependentPeriodIds, $reason, $actor): Collection {
            $period = AccountingPostingPeriod::query()->lockForUpdate()->findOrFail($periodId);
            if ($period->status !== 'closed') {
                throw ValidationException::withMessages(['period' => 'Only a closed month can be reopened.']);
            }
            $laterClosed = AccountingPostingPeriod::query()->where('book_key', $period->book_key)->where('status', 'closed')
                ->where('starts_on', '>', $period->starts_on)->orderBy('starts_on')->lockForUpdate()->get();
            $requestedIds = collect($dependentPeriodIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
            $requiredIds = $laterClosed->modelKeys();
            sort($requiredIds);
            if ($requestedIds->all() !== $requiredIds) {
                throw ValidationException::withMessages([
                    'dependentPeriodIds' => 'Explicitly authorize every later closed accounting month: '.implode(', ', $laterClosed->pluck('starts_on')->map(fn ($date) => substr((string) $date, 0, 7))->all()).'.',
                ]);
            }

            $periods = $laterClosed->prepend($period);

            app(FiscalYearClosingService::class)->reverseClosingsForReopen($periods, $reason, $actor);
            foreach ($periods as $affected) {
                $old = ['status' => $affected->status];
                $affected->forceFill([
                    'status' => 'open', 'reopened_at' => now(), 'reopened_by' => $actor->id,
                    'reopen_reason' => trim($reason),
                ])->save();
                ActivityAudit::recordChange($affected, 'Accounting month reopened', [
                    'status' => 'open', 'reopened_by' => $actor->id, 'reopen_reason' => trim($reason),
                ], $old);
            }

            return $periods->values();
        });
    }
}
