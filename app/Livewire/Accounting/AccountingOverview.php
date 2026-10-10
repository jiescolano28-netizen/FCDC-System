<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use Carbon\CarbonImmutable;
use Livewire\Component;

class AccountingOverview extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    private const INCOME_CLASSIFICATIONS = ['sales', 'other_income'];

    private const EXPENSE_CLASSIFICATIONS = ['cost_of_goods_sold', 'operating_expense', 'other_expense'];

    private const POSITION_CLASSIFICATIONS = [
        'accounts_payable', 'accounts_receivable', 'cash', 'bank', 'inventory', 'equipment',
        'card_clearing', 'other_current_asset', 'other_noncurrent_asset', 'output_vat',
        'other_current_liability', 'other_noncurrent_liability', 'input_vat',
    ];

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->endOfMonth()->toDateString();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        $report = $this->report();

        return view('livewire.accounting.accounting-overview', [
            'report' => $report,
        ])->layout('layouts.app', ['title' => 'Accounting Overview']);
    }

    private function report(): array
    {
        if (! $this->validDate($this->fromDate) || ! $this->validDate($this->toDate) || $this->fromDate > $this->toDate) {
            return ['available' => false, 'error' => 'Select a valid inclusive Manila reporting period.', 'cards' => [], 'journals' => collect()];
        }

        $cutover = AccountingJournal::query()
            ->where('book_key', 'FCDC')->where('source_type', 'opening')->where('source_id', 'FCDC')
            ->where('status', 'posted')->value('accounting_date');
        if (! $cutover) {
            return ['available' => false, 'error' => 'Accounting results are unavailable until approved opening balances establish accounting coverage.', 'cards' => [], 'journals' => collect()];
        }
        $cutoverDate = CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString();
        if ($this->fromDate < $cutoverDate) {
            return ['available' => false, 'error' => 'Selected period begins before approved accounting cutover coverage.', 'cards' => [], 'journals' => collect()];
        }

        $incomeClassifications = [...self::INCOME_CLASSIFICATIONS, ...self::EXPENSE_CLASSIFICATIONS];
        $incomeAccounts = AccountingAccount::query()->whereIn('type', ['Revenue', 'Expense'])
            ->where(fn ($query) => $query->whereIn('classification', $incomeClassifications)->orWhere('is_active', true))
            ->get();
        if ($incomeAccounts->contains(fn (AccountingAccount $account) => $account->is_active && (
            $account->approved_at === null || ! in_array($account->classification, $incomeClassifications, true)
        ))) {
            return ['available' => false, 'error' => 'Approved Revenue and Expense classifications are unavailable.', 'cards' => [], 'journals' => collect()];
        }
        $incomeAccounts = $incomeAccounts->filter(fn (AccountingAccount $account) => $account->approved_at !== null
            && in_array($account->classification, $incomeClassifications, true));
        if ($incomeAccounts->isEmpty()) {
            return ['available' => false, 'error' => 'Approved Revenue and Expense classifications are unavailable.', 'cards' => [], 'journals' => collect()];
        }

        $postedPositionAccountIds = AccountingJournalLine::query()
            ->whereHas('journal', fn ($query) => $query->where('book_key', 'FCDC')->where('status', 'posted')
                ->whereDate('accounting_date', '>=', $cutoverDate)->whereDate('accounting_date', '<=', $this->toDate))
            ->distinct()->pluck('accounting_account_id');
        $balanceAccounts = AccountingAccount::query()->whereIn('type', ['Asset', 'Liability'])
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $postedPositionAccountIds))
            ->get();
        if ($balanceAccounts->contains(fn (AccountingAccount $account) => $account->approved_at === null
            || ! in_array($account->classification, self::POSITION_CLASSIFICATIONS, true))) {
            return ['available' => false, 'error' => 'Approved Asset and Liability classifications are unavailable.', 'cards' => [], 'journals' => collect()];
        }

        $closingIds = AccountingJournal::query()->where('book_key', 'FCDC')
            ->where('source_type', 'fiscal_year_closing')->pluck('id')->all();
        $excludedIds = $this->closingChainIds($closingIds);
        $periodLines = AccountingJournalLine::query()->with(['account', 'journal'])
            ->whereHas('journal', function ($query) use ($cutoverDate, $excludedIds): void {
                $query->where('book_key', 'FCDC')->where('status', 'posted')
                    ->whereDate('accounting_date', '>=', max($this->fromDate, $cutoverDate))
                    ->whereDate('accounting_date', '<=', $this->toDate)
                    ->where('source_type', '!=', 'opening');
                if ($excludedIds !== []) {
                    $query->whereNotIn('id', $excludedIds);
                }
            })->whereIn('accounting_account_id', $incomeAccounts->pluck('id'))->get();

        $revenueCents = 0;
        $expensesCents = 0;
        foreach ($periodLines as $line) {
            $account = $line->account;
            $amount = $account->type === 'Revenue'
                ? (int) $line->credit_cents - (int) $line->debit_cents
                : (int) $line->debit_cents - (int) $line->credit_cents;
            if (in_array($account->classification, self::INCOME_CLASSIFICATIONS, true)) {
                $revenueCents += $amount;
            } elseif (in_array($account->classification, self::EXPENSE_CLASSIFICATIONS, true)) {
                $expensesCents += $amount;
            }
        }

        $positionLines = AccountingJournalLine::query()
            ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
            ->where('accounting_journals.book_key', 'FCDC')->where('accounting_journals.status', 'posted')
            ->whereDate('accounting_journals.accounting_date', '>=', $cutoverDate)
            ->whereDate('accounting_journals.accounting_date', '<=', $this->toDate)
            ->whereIn('accounting_account_id', $balanceAccounts->pluck('id'))
            ->select('accounting_journal_lines.*')->get()->groupBy('accounting_account_id');

        $positions = [];
        foreach (self::POSITION_CLASSIFICATIONS as $classification) {
            $positions[$classification] = 0;
        }
        foreach ($balanceAccounts as $account) {
            $debitNet = (int) $positionLines->get($account->id, collect())->sum(
                fn (AccountingJournalLine $line) => (int) $line->debit_cents - (int) $line->credit_cents,
            );
            $balance = $account->type === 'Asset' ? $debitNet : -$debitNet;
            $positions[$account->classification] += $balance;
        }

        $hasActivity = AccountingJournal::query()->where('book_key', 'FCDC')->where('status', 'posted')
            ->whereDate('accounting_date', '>=', $this->fromDate)->whereDate('accounting_date', '<=', $this->toDate)
            ->where('source_type', '!=', 'opening')
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->exists();
        $cards = [
            ['label' => 'Revenue', 'amount' => $revenueCents, 'note' => 'VAT-exclusive income for selected period'],
            ['label' => 'Expenses', 'amount' => $expensesCents, 'note' => 'Includes cost of goods sold and all expenses'],
            ['label' => 'Net Income', 'amount' => $revenueCents - $expensesCents, 'note' => 'Revenue less expenses for selected period'],
            ['label' => 'AP outstanding', 'amount' => $positions['accounts_payable'], 'note' => 'As of period end'],
            ['label' => 'GL-only AR', 'amount' => $positions['accounts_receivable'], 'note' => 'General Ledger receivable as of period end'],
            ['label' => 'Cash & Bank', 'amount' => $positions['cash'] + $positions['bank'], 'note' => 'As of period end; excludes card clearing'],
            ['label' => 'Inventory', 'amount' => $positions['inventory'], 'note' => 'Valued General Ledger balance as of period end'],
        ];
        $journals = AccountingJournal::query()->where('book_key', 'FCDC')->where('status', 'posted')
            ->where('source_type', '!=', 'opening')
            ->orderByDesc('accounting_date')->orderByDesc('id')->limit(6)->get();

        return [
            'available' => true, 'error' => null, 'cards' => $cards, 'journals' => $journals,
            'hasActivity' => $hasActivity, 'from' => $this->fromDate, 'to' => $this->toDate,
        ];
    }

    /** @param array<int, int> $closingIds
     * @return array<int, int>
     */
    private function closingChainIds(array $closingIds): array
    {
        $excluded = $closingIds;
        $frontier = $closingIds;
        while ($frontier !== []) {
            $next = AccountingJournal::query()->where('book_key', 'FCDC')
                ->whereIn('correction_of_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($next, $excluded));
            $excluded = [...$excluded, ...$frontier];
        }

        return $excluded;
    }

    private function validDate(string $date): bool
    {
        if (! CarbonImmutable::hasFormat($date, 'Y-m-d')) {
            return false;
        }
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
