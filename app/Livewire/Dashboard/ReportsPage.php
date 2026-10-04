<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use App\Support\IllustrativeSales;
use Livewire\Component;

class ReportsPage extends Component
{
    public string $salesPeriod = 'week';

    private const SALES_PERIOD_LABELS = [
        'week' => 'This week · illustrative sample',
        'month' => 'This month · illustrative sample',
        'year' => 'This year · illustrative sample',
    ];

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
        $sales = session('demo.pos.employee.'.auth()->id().'.sales', []);
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

        return view('livewire.dashboard.reports-page', [
            'sales' => $sales,
            'period' => $period,
            'periodTotal' => array_sum(array_column($period['data'], 'total')),
            'inventoryCount' => Inventory::query()->count(),
            'inventoryValue' => (float) Inventory::query()->selectRaw('COALESCE(SUM(qty * unit_cost), 0) as value')->value('value'),
            'categoryValues' => $categoryValues,
            'inventoryQuantities' => $inventoryQuantities,
        ])->layout('layouts.app', ['title' => 'Reports']);
    }
}
