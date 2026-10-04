<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use App\Support\IllustrativeSales;
use Livewire\Component;

class DashboardPage extends Component
{
    public string $salesPeriod = 'week';

    private const SALES_PERIODS = [
        'week' => ['title' => 'Sales this week', 'subLabel' => 'Mon–Sun'],
        'month' => ['title' => 'Sales this month', 'subLabel' => 'By week'],
        'year' => ['title' => 'Sales this year', 'subLabel' => 'Jan–Dec'],
    ];

    public function setSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIODS[$period])) {
            return;
        }

        $this->salesPeriod = $period;
        $this->dispatch('dashboard-chart-update', sales: IllustrativeSales::SERIES_BY_PERIOD[$period]);
    }

    public function updatedSalesPeriod(string $period): void
    {
        if (isset(self::SALES_PERIODS[$period])) {
            return;
        }

        $this->salesPeriod = 'week';
        $this->dispatch('dashboard-chart-update', sales: IllustrativeSales::SERIES_BY_PERIOD['week']);
    }

    public function render()
    {
        $period = self::SALES_PERIODS[$this->salesPeriod] + [
            'data' => IllustrativeSales::SERIES_BY_PERIOD[$this->salesPeriod],
        ];
        $sales = session('demo.pos.employee.'.auth()->id().'.sales', []);
        $lowStockItems = Inventory::query()
            ->whereColumn('qty', '<=', 'reorder_level')
            ->orderBy('name')
            ->get();
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

        return view('livewire.dashboard.dashboard-page', [
            'inventoryCount' => Inventory::query()->count(),
            'inventoryValue' => (float) Inventory::query()->selectRaw('COALESCE(SUM(qty * unit_cost), 0) as value')->value('value'),
            'lowStockItems' => $lowStockItems,
            'sales' => $sales,
            'period' => $period,
            'periodTotal' => array_sum(array_column($period['data'], 'total')),
            'categoryValues' => $categoryValues,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
