<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Support\IllustrativeSales;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Livewire\Component;

class ReportsPage extends Component
{
    public string $salesPeriod = 'week';

    public string $reportPeriod = 'daily';

    public string $reportDate;

    private const SALES_PERIOD_LABELS = [
        'week' => 'This week · illustrative sample',
        'month' => 'This month · illustrative sample',
        'year' => 'This year · illustrative sample',
    ];

    public function mount(): void
    {
        $this->reportDate = now()->toDateString();
    }

    public function updatedReportPeriod(string $period): void
    {
        $this->validateOnly('reportPeriod', ['reportPeriod' => ['required', 'in:daily,monthly']]);
    }

    public function updatedReportDate(): void
    {
        $this->validateOnly('reportDate', ['reportDate' => ['required', 'date_format:Y-m-d']]);
    }

    public function setSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIOD_LABELS[$period])) {
            return;
        }

        $this->salesPeriod = $period;
        $this->dispatch('reports-chart-update', sales: IllustrativeSales::SERIES_BY_PERIOD[$period]);
    }

    public function updatedSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIOD_LABELS[$period])) {
            $this->salesPeriod = 'week';
            $this->dispatch('reports-chart-update', sales: IllustrativeSales::SERIES_BY_PERIOD['week']);
        }
    }

    public function render()
    {
        $sales = PosTransaction::query()->with('lines')->where('status', 'completed')->latest('completed_at')->limit(20)->get();
        $period = [
            'label' => self::SALES_PERIOD_LABELS[$this->salesPeriod],
            'data' => IllustrativeSales::SERIES_BY_PERIOD[$this->salesPeriod],
        ];
        $categoryValues = Inventory::query()
            ->selectRaw('category, SUM(qty * unit_cost) as value')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn (Inventory $item) => [
                'label' => $item->category,
                'total' => (float) $item->value,
            ])
            ->all();
        $inventoryQuantities = Inventory::query()
            ->selectRaw('category, unit, SUM(qty) as quantity')
            ->groupBy('category', 'unit')
            ->orderBy('category')
            ->orderBy('unit')
            ->get();

        try {
            $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $this->reportDate, config('app.timezone'));
        } catch (InvalidFormatException) {
            $selectedDate = CarbonImmutable::now(config('app.timezone'));
        }

        if ($selectedDate->format('Y-m-d') !== $this->reportDate) {
            $selectedDate = CarbonImmutable::now(config('app.timezone'));
        }
        $start = $this->reportPeriod === 'monthly' ? $selectedDate->startOfMonth() : $selectedDate->startOfDay();
        $end = $this->reportPeriod === 'monthly' ? $selectedDate->endOfMonth() : $selectedDate->endOfDay();
        $transactions = PosTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end]);
        $transactionIds = (clone $transactions)->select('id');
        $posSales = [
            'total' => (float) (clone $transactions)->sum('total'),
            'count' => (clone $transactions)->count(),
            'items' => PosTransactionLine::query()
                ->whereIn('pos_transaction_id', $transactionIds)
                ->selectRaw('inventory_code, item_name, unit, SUM(quantity) as quantity, SUM(line_total) as total')
                ->groupBy('inventory_code', 'item_name', 'unit')
                ->orderBy('item_name')
                ->get(),
            'categories' => PosTransactionLine::query()
                ->whereIn('pos_transaction_id', $transactionIds)
                ->selectRaw('category, SUM(line_total) as total')
                ->groupBy('category')
                ->orderBy('category')
                ->get(),
            'payments' => (clone $transactions)
                ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get(),
        ];

        return view('livewire.dashboard.reports-page', [
            'sales' => $sales,
            'period' => $period,
            'periodTotal' => array_sum(array_column($period['data'], 'total')),
            'inventoryCount' => Inventory::query()->count(),
            'inventoryValue' => (float) Inventory::query()->selectRaw('COALESCE(SUM(qty * unit_cost), 0) as value')->value('value'),
            'categoryValues' => $categoryValues,
            'inventoryQuantities' => $inventoryQuantities,
            'posSales' => $posSales,
            'reportStart' => $start,
        ])->layout('layouts.app', ['title' => 'Reports']);
    }
}
