<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingYtdSummary;
use Carbon\CarbonImmutable;
use Livewire\Component;

class FinancialStatements extends Component
{
    private const INCOME_CLASSIFICATIONS = [
        'sales', 'cost_of_goods_sold', 'operating_expense', 'other_income', 'other_expense',
    ];

    public string $statementType = 'income-statement';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->toDateString();
    }

    public function render()
    {
        $statementTitle = $this->statementType === 'balance-sheet' ? 'Balance Sheet' : 'Income Statement';
        $report = $this->statementType === 'income-statement' ? $this->incomeStatement() : null;

        return view('livewire.accounting.financial-statements', [
            'statementTitle' => $statementTitle,
            'report' => $report,
            'generatedAt' => CarbonImmutable::now('Asia/Manila')->format('F j, Y g:i:s A').' Asia/Manila',
        ])->layout('layouts.app', ['title' => 'Financial Statements']);
    }

    private function incomeStatement(): array
    {
        if (! $this->validDate($this->fromDate) || ! $this->validDate($this->toDate) || $this->fromDate > $this->toDate) {
            return ['error' => 'Select a valid inclusive reporting period.', 'available' => false];
        }

        $cutover = AccountingJournal::query()
            ->where('book_key', 'FCDC')
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->where('status', 'posted')
            ->value('accounting_date');
        if ($cutover === null) {
            return ['error' => 'Posted accounting cutover coverage is unavailable until approved opening balances are posted.', 'available' => false];
        }
        $cutoverDate = CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString();
        $summary = null;
        if ($this->fromDate < $cutoverDate) {
            $yearStart = CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->startOfYear()->toDateString();
            $priorDay = CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->subDay()->toDateString();
            if ($this->fromDate !== $yearStart || $this->toDate < $priorDay
                || CarbonImmutable::parse($this->toDate, 'Asia/Manila')->year !== CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->year) {
                return ['error' => 'Selected range begins before approved accounting cutover coverage.', 'available' => false];
            }
            $summary = AccountingYtdSummary::query()
                ->where('book_key', 'FCDC')
                ->where('fiscal_year', CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->year)
                ->where('status', 'approved')
                ->with('lines.account')
                ->first();
            if (! $summary || $summary->through_date->toDateString() !== $priorDay) {
                return ['error' => 'Approved pre-cutover YTD summary coverage is unavailable for this period.', 'available' => false];
            }
        }

        $baseAccounts = AccountingAccount::query()
            ->whereIn('type', ['Revenue', 'Expense'])
            ->whereIn('classification', self::INCOME_CLASSIFICATIONS);
        if ((clone $baseAccounts)->where('is_active', true)->whereNull('approved_at')->exists()) {
            return ['error' => 'Approved account classifications are unavailable while active income or expense accounts await approval.', 'available' => false];
        }
        $accounts = $baseAccounts
            ->whereNotNull('approved_at')
            ->get()
            ->keyBy('id');
        if ($accounts->isEmpty()) {
            return ['error' => 'Approved Revenue and Expense classifications are unavailable.', 'available' => false];
        }

        $closingIds = AccountingJournal::query()
            ->where('book_key', 'FCDC')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(source_type) LIKE ?', ['%clos%']);
            })->pluck('id');
        $excludedIds = $closingIds->all();
        $frontier = $excludedIds;
        while ($frontier !== []) {
            $next = AccountingJournal::query()
                ->where('book_key', 'FCDC')
                ->whereIn('correction_of_id', $frontier)
                ->pluck('id')
                ->all();
            $frontier = array_values(array_diff($next, $excludedIds));
            $excludedIds = [...$excludedIds, ...$frontier];
        }

        $activity = AccountingJournalLine::query()
            ->whereHas('journal', function ($query) use ($cutoverDate, $excludedIds): void {
                $query->where('book_key', 'FCDC')
                    ->where('status', 'posted')
                    ->whereDate('accounting_date', '>=', max($this->fromDate, $cutoverDate))
                    ->whereDate('accounting_date', '<=', $this->toDate)
                    ->where('source_type', '!=', 'opening');
                if ($excludedIds !== []) {
                    $query->whereNotIn('id', $excludedIds);
                }
            })
            ->whereIn('accounting_account_id', $accounts->keys())
            ->get()
            ->groupBy('accounting_account_id');

        $totals = ['sales' => 0, 'cogs' => 0, 'operating' => 0, 'other_income' => 0, 'other_expense' => 0];
        foreach ($accounts as $id => $account) {
            $amount = $activity->get($id, collect())->sum(function (AccountingJournalLine $line) use ($account): int {
                return in_array($account->type, ['Revenue'], true)
                    ? (int) $line->credit_cents - (int) $line->debit_cents
                    : (int) $line->debit_cents - (int) $line->credit_cents;
            });
            if ($summary) {
                $summaryLine = $summary->lines->first(
                    fn ($line) => $line->accounting_account_id === $account->id && $line->account?->approved_at !== null,
                );
                if ($summaryLine) {
                    $expectedNormalBalance = $account->type === 'Revenue' ? 'credit' : 'debit';
                    $amount += $summaryLine->amount_cents * ($account->normal_balance === $expectedNormalBalance ? 1 : -1);
                }
            }
            $bucket = match ($account->classification) {
                'sales' => 'sales',
                'cost_of_goods_sold' => 'cogs',
                'operating_expense' => 'operating',
                'other_income' => 'other_income',
                'other_expense' => 'other_expense',
            };
            $totals[$bucket] += $amount;
        }

        $grossProfit = $totals['sales'] - $totals['cogs'];
        $netIncome = $grossProfit - $totals['operating'] + $totals['other_income'] - $totals['other_expense'];

        return [
            'available' => true,
            'error' => null,
            'from' => $this->fromDate,
            'to' => $this->toDate,
            'cutover' => $cutoverDate,
            'summary' => $summary,
            'hasActivity' => $activity->isNotEmpty() || $summary?->lines->isNotEmpty(),
            'sales' => $totals['sales'],
            'cogs' => $totals['cogs'],
            'grossProfit' => $grossProfit,
            'operating' => $totals['operating'],
            'otherIncome' => $totals['other_income'],
            'otherExpense' => $totals['other_expense'],
            'netIncome' => $netIncome,
        ];
    }

    private function validDate(string $date): bool
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
