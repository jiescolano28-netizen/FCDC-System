<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingYtdSummary;
use App\Services\Accounting\OpeningBooksService;
use Carbon\CarbonImmutable;
use Livewire\Component;

class FinancialStatements extends Component
{
    private const FISCAL_YEAR_CLOSING_SOURCE_TYPE = 'fiscal_year_closing';

    private const INCOME_CLASSIFICATIONS = [
        'sales', 'cost_of_goods_sold', 'operating_expense', 'other_income', 'other_expense',
    ];

    private const BALANCE_CLASSIFICATIONS = [
        'cash', 'bank', 'accounts_receivable', 'card_clearing', 'inventory', 'equipment',
        'other_current_asset', 'other_noncurrent_asset', 'accounts_payable', 'output_vat',
        'other_current_liability', 'other_noncurrent_liability', 'input_vat', 'capital',
        'retained_earnings', 'other_equity',
    ];

    private const BALANCE_CLASSIFICATION_LABELS = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'accounts_receivable' => 'Accounts receivable',
        'card_clearing' => 'Card settlement receivable / clearing',
        'inventory' => 'Inventory',
        'equipment' => 'Equipment',
        'other_current_asset' => 'Other current asset',
        'other_noncurrent_asset' => 'Other non-current asset',
        'accounts_payable' => 'Accounts payable',
        'output_vat' => 'Output VAT',
        'other_current_liability' => 'Other current liability',
        'other_noncurrent_liability' => 'Other non-current liability',
        'input_vat' => 'Input VAT',
        'capital' => 'Capital',
        'retained_earnings' => 'Retained / accumulated earnings',
        'other_equity' => 'Other equity',
    ];

    private const BALANCE_TYPES = [
        'Asset' => ['cash', 'bank', 'accounts_receivable', 'card_clearing', 'inventory', 'equipment', 'other_current_asset', 'other_noncurrent_asset', 'input_vat'],
        'Liability' => ['accounts_payable', 'output_vat', 'other_current_liability', 'other_noncurrent_liability'],
        'Equity' => ['capital', 'retained_earnings', 'other_equity'],
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
        abort_unless(auth()->user()?->can('accounting.view'), 403);
        $readiness = app(OpeningBooksService::class)->readiness();
        $statementTitle = $this->statementType === 'balance-sheet' ? 'Balance Sheet' : 'Income Statement';
        $report = $readiness['production_activated']
            ? match ($this->statementType) {
                'income-statement' => $this->incomeStatement(),
                'balance-sheet' => $this->balanceSheet(),
                default => ['available' => false, 'error' => 'Select a supported financial statement.'],
            }
            : ['available' => false, 'error' => $readiness['production']];

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

        $cutover = $this->accountingCutoverDate();
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
            ->where('source_type', self::FISCAL_YEAR_CLOSING_SOURCE_TYPE)
            ->pluck('id');
        $excludedIds = $this->fiscalClosingExclusionIds($closingIds->all());

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
    private function balanceSheet(): array
    {
        if (! $this->validDate($this->toDate)) {
            return ['available' => false, 'error' => 'Select a valid Manila as-of date.'];
        }

        $cutover = $this->accountingCutoverDate();
        if ($cutover === null) {
            return ['available' => false, 'error' => 'Posted accounting cutover coverage is unavailable until approved opening balances are posted.'];
        }
        $cutoverDate = CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString();
        if ($this->toDate < $cutoverDate) {
            return ['available' => false, 'error' => 'Selected as-of date precedes approved accounting cutover coverage.'];
        }

        $accounts = AccountingAccount::query()->whereIn('type', array_keys(self::BALANCE_TYPES))->get();
        $postedAccountIds = AccountingJournalLine::query()
            ->whereHas('journal', fn ($query) => $query->where('book_key', 'FCDC')
                ->where('status', 'posted')->whereDate('accounting_date', '>=', $cutoverDate)
                ->whereDate('accounting_date', '<=', $this->toDate))
            ->distinct()->pluck('accounting_account_id');
        foreach (self::BALANCE_TYPES as $type => $classifications) {
            if ($accounts->contains(fn (AccountingAccount $account) => $account->type === $type
                && ($account->is_active || $account->approved_at !== null || $postedAccountIds->contains($account->id))
                && ($account->approved_at === null || ! in_array($account->classification, $classifications, true)))) {
                return ['available' => false, 'error' => 'Approved asset, liability and equity classifications are unavailable while relevant accounts await approval or have an unsupported classification.'];
            }
        }

        $approvedAccounts = $accounts->filter(fn (AccountingAccount $account) => $account->approved_at !== null
            && in_array($account->classification, self::BALANCE_CLASSIFICATIONS, true))->keyBy('id');
        if ($approvedAccounts->isEmpty()) {
            return ['available' => false, 'error' => 'Approved Asset, Liability and Equity classifications are unavailable.'];
        }

        $lines = AccountingJournalLine::query()
            ->whereHas('journal', fn ($query) => $query->where('book_key', 'FCDC')
                ->where('status', 'posted')->whereDate('accounting_date', '>=', $cutoverDate)
                ->whereDate('accounting_date', '<=', $this->toDate))
            ->whereIn('accounting_account_id', $approvedAccounts->keys())
            ->get()
            ->groupBy('accounting_account_id');
        $sections = ['Asset' => [], 'Liability' => [], 'Equity' => []];
        $totals = ['Asset' => 0, 'Liability' => 0, 'Equity' => 0];
        foreach ($approvedAccounts as $id => $account) {
            $debitNet = (int) $lines->get($id, collect())->sum(
                fn (AccountingJournalLine $line) => (int) $line->debit_cents - (int) $line->credit_cents,
            );
            $amount = $account->type === 'Asset' ? $debitNet : -$debitNet;
            if ($amount !== 0) {
                $sections[$account->type][] = [
                    'label' => self::BALANCE_CLASSIFICATION_LABELS[$account->classification],
                    'account' => $account->name,
                    'amount' => $amount,
                ];
                $totals[$account->type] += $amount;
            }
        }

        [$unclosedEarnings, $earningsError] = $this->unclosedEarnings($cutoverDate);
        if ($earningsError !== null) {
            return ['available' => false, 'error' => $earningsError];
        }
        $sections['Equity'][] = ['label' => 'Unclosed earnings', 'account' => null, 'amount' => $unclosedEarnings];
        $totals['Equity'] += $unclosedEarnings;

        $assets = $totals['Asset'];
        $liabilitiesAndEquity = $totals['Liability'] + $totals['Equity'];

        return [
            'available' => true,
            'error' => null,
            'asOf' => $this->toDate,
            'sections' => $sections,
            'assets' => $assets,
            'liabilities' => $totals['Liability'],
            'equity' => $totals['Equity'],
            'liabilitiesAndEquity' => $liabilitiesAndEquity,
            'balanced' => $assets === $liabilitiesAndEquity,
        ];
    }

    /** @return array{int, ?string} */
    private function unclosedEarnings(string $cutoverDate): array
    {
        $yearStart = CarbonImmutable::parse($this->toDate, 'Asia/Manila')->startOfYear()->toDateString();
        $summary = null;
        if ($yearStart < $cutoverDate) {
            $summary = AccountingYtdSummary::query()
                ->where('book_key', 'FCDC')
                ->where('fiscal_year', CarbonImmutable::parse($this->toDate, 'Asia/Manila')->year)
                ->where('status', 'approved')
                ->with('lines.account')
                ->first();
            $priorDay = CarbonImmutable::parse($cutoverDate, 'Asia/Manila')->subDay()->toDateString();
            if (! $summary || $summary->through_date->toDateString() !== $priorDay) {
                return [0, 'Approved pre-cutover YTD summary coverage is required to calculate current unclosed earnings.'];
            }
        }

        $closingIds = AccountingJournal::query()
            ->where('book_key', 'FCDC')->where('source_type', self::FISCAL_YEAR_CLOSING_SOURCE_TYPE)
            ->where('status', 'posted')
            ->whereYear('accounting_date', CarbonImmutable::parse($this->toDate, 'Asia/Manila')->year)
            ->whereDate('accounting_date', '<=', $this->toDate)->pluck('id');
        $reversedClosingIds = AccountingJournal::query()
            ->where('book_key', 'FCDC')->where('status', 'posted')
            ->whereDate('accounting_date', '<=', $this->toDate)
            ->whereIn('correction_of_id', $closingIds)->pluck('correction_of_id');
        if ($closingIds->diff($reversedClosingIds)->isNotEmpty()) {
            return [0, null];
        }

        $fromDate = max($yearStart, $cutoverDate);
        $incomeAccountsQuery = AccountingAccount::query()
            ->whereIn('type', ['Revenue', 'Expense'])
            ->whereIn('classification', self::INCOME_CLASSIFICATIONS);
        if ((clone $incomeAccountsQuery)->where('is_active', true)->whereNull('approved_at')->exists()) {
            return [0, 'Approved income and expense classifications are unavailable while active accounts await approval.'];
        }
        $incomeAccounts = $incomeAccountsQuery
            ->where(fn ($query) => $query->whereNotNull('approved_at')
                ->orWhere(fn ($inactive) => $inactive->where('is_active', false)->whereNotNull('used_at')))
            ->get()->keyBy('id');
        $excludedIds = $this->fiscalClosingExclusionIds($closingIds->all());
        $activity = AccountingJournalLine::query()
            ->whereHas('journal', function ($query) use ($fromDate, $excludedIds): void {
                $query->where('book_key', 'FCDC')->where('status', 'posted')
                    ->whereDate('accounting_date', '>=', $fromDate)
                    ->whereDate('accounting_date', '<=', $this->toDate)
                    ->where('source_type', '!=', 'opening');
                if ($excludedIds !== []) {
                    $query->whereNotIn('id', $excludedIds);
                }
            })
            ->whereIn('accounting_account_id', $incomeAccounts->keys())
            ->get()->groupBy('accounting_account_id');
        $earnings = 0;
        foreach ($incomeAccounts as $id => $account) {
            $earnings += (int) $activity->get($id, collect())->sum(
                fn (AccountingJournalLine $line) => ($account->type === 'Revenue' ? 1 : -1)
                    * ($account->type === 'Revenue'
                        ? (int) $line->credit_cents - (int) $line->debit_cents
                        : (int) $line->debit_cents - (int) $line->credit_cents),
            );
            $summaryLine = $summary?->lines->first(
                fn ($line) => $line->accounting_account_id === $account->id && $line->account !== null,
            );
            if ($summaryLine) {
                $expectedNormalBalance = $account->type === 'Revenue' ? 'credit' : 'debit';
                $earnings += ($account->type === 'Revenue' ? 1 : -1) * (int) $summaryLine->amount_cents
                    * ($account->normal_balance === $expectedNormalBalance ? 1 : -1);
            }
        }

        return [$earnings, null];
    }


    /**
     * @param array<int, int> $closingIds
     * @return array<int, int>
     */
    private function fiscalClosingExclusionIds(array $closingIds): array
    {
        $excludedIds = $closingIds;
        $frontier = $excludedIds;
        while ($frontier !== []) {
            $next = AccountingJournal::query()->where('book_key', 'FCDC')
                ->whereIn('correction_of_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($next, $excludedIds));
            $excludedIds = [...$excludedIds, ...$frontier];
        }

        return $excludedIds;
    }

    private function accountingCutoverDate(): ?string
    {
        return AccountingJournal::query()
            ->where('book_key', 'FCDC')
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->where('status', 'posted')
            ->value('accounting_date');
    }

    private function validDate(string $date): bool
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
