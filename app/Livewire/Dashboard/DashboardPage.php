<?php

namespace App\Livewire\Dashboard;

use App\Models\AccountingJournalLine;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Livewire\Component;

class DashboardPage extends Component
{
    public string $period = 'month';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->toDateString();
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', 'week', 'month', 'year', 'custom'], true)) {
            return;
        }
        $this->period = $period;
        if ($period === 'custom') {
            return;
        }
        $today = CarbonImmutable::now('Asia/Manila');
        [$start, $end] = match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'week' => [$today->startOfWeek(), $today->endOfDay()],
            'year' => [$today->startOfYear(), $today->endOfDay()],
            default => [$today->startOfMonth(), $today->endOfDay()],
        };
        $this->fromDate = $start->toDateString();
        $this->toDate = $end->toDateString();
    }

    public function updatedFromDate(): void
    {
        $this->period = 'custom';
    }

    public function updatedToDate(): void
    {
        $this->period = 'custom';
    }

    public function render()
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $start = $this->parseDate($this->fromDate) ?? $today->startOfMonth();
        $end = ($this->parseDate($this->toDate) ?? $today)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }
        $this->fromDate = $start->toDateString();
        $this->toDate = $end->toDateString();

        $transactions = PosTransaction::query()->where('status', 'completed')->whereBetween('completed_at', [$start, $end]);
        $salesByDate = (clone $transactions)->selectRaw('DATE(completed_at) as date, SUM(total) as total')->groupBy('date')->orderBy('date')->get()->keyBy('date');
        $vatByDate = PosVatRecord::query()->whereBetween('completed_at', [$start, $end])
            ->selectRaw('DATE(completed_at) as date, SUM(output_vat) as total')->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $journalLines = AccountingJournalLine::query()->with(['account', 'journal'])
            ->whereHas('journal', fn ($query) => $query->where('book_key', 'FCDC')->where('status', 'posted')->whereBetween('accounting_date', [$start->toDateString(), $end->toDateString()]))
            ->whereHas('account', fn ($query) => $query->whereNotNull('approved_at'))
            ->get();
        $accountingByDate = [];
        foreach ($journalLines as $line) {
            $date = $line->journal->accounting_date->toDateString();
            $accountingByDate[$date] ??= ['revenue' => 0, 'expenses' => 0];
            if ($line->account->type === 'Revenue' && in_array($line->account->classification, ['sales', 'contra_revenue', 'other_income'], true)) {
                $accountingByDate[$date]['revenue'] += ((int) $line->credit_cents - (int) $line->debit_cents) / 100;
            } elseif ($line->account->type === 'Expense' && in_array($line->account->classification, ['cost_of_goods_sold', 'operating_expense', 'other_expense'], true)) {
                $accountingByDate[$date]['expenses'] += ((int) $line->debit_cents - (int) $line->credit_cents) / 100;
            }
        }

        $bucketByMonth = $start->diffInDays($end) > 62;
        $sales = $revenue = $expenses = $vat = [];
        $cursor = $start->startOfDay();
        $last = $end->startOfDay();
        while ($cursor <= $last) {
            $key = $bucketByMonth ? $cursor->format('Y-m') : $cursor->toDateString();
            $label = $bucketByMonth ? $cursor->format('M Y') : $cursor->format('M j');
            $sales[$key] ??= ['label' => $label, 'total' => 0];
            $revenue[$key] ??= ['label' => $label, 'total' => 0];
            $expenses[$key] ??= ['label' => $label, 'total' => 0];
            $vat[$key] ??= ['label' => $label, 'total' => 0];
            $date = $cursor->toDateString();
            $sales[$key]['total'] += (float) ($salesByDate->get($date)?->total ?? 0);
            $vat[$key]['total'] += (float) ($vatByDate->get($date)?->total ?? 0);
            $revenue[$key]['total'] += $accountingByDate[$date]['revenue'] ?? 0;
            $expenses[$key]['total'] += $accountingByDate[$date]['expenses'] ?? 0;
            $cursor = $cursor->addDay();
        }

        $inventory = Inventory::query();
        $inventoryItems = (clone $inventory)->orderBy('name')->get();
        $lowStockItems = $inventoryItems->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_LOW_STOCK)->values();
        $categoryValues = (clone $inventory)->selectRaw('category, SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)) as value')
            ->groupBy('category')->orderBy('category')->get()->map(fn (Inventory $item) => ['label' => $item->category, 'total' => (float) $item->value])->all();

        return view('livewire.dashboard.dashboard-page', [
            'inventoryCount' => $inventoryItems->count(),
            'inventoryValue' => (float) (clone $inventory)->selectRaw('COALESCE(SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)), 0) as value')->value('value'),
            'lowStockCount' => $lowStockItems->count(),
            'stockAlerts' => $lowStockItems->sortBy('name')->values(),
            'outOfStockCount' => $inventoryItems->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_OUT_OF_STOCK)->count(),
            'recentInventoryItems' => (clone $inventory)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(),
            'categoryValues' => $categoryValues,
            'salesTotal' => (float) (clone $transactions)->sum('total'),
            'revenueTotal' => array_sum(array_column($revenue, 'total')),
            'expensesTotal' => array_sum(array_column($expenses, 'total')),
            'vatTotal' => (float) PosVatRecord::query()->whereBetween('completed_at', [$start, $end])->sum('output_vat'),
            'salesChart' => array_values($sales),
            'accountingChart' => ['revenue' => array_values($revenue), 'expenses' => array_values($expenses)],
            'vatChart' => array_values($vat),
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (InvalidFormatException) {
            return null;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }
}
