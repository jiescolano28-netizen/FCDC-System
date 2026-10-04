<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use Livewire\Component;

class ReportsPage extends Component
{
    public string $salesPeriod = 'week';

    private const SALES_PERIODS = [
        'week' => [
            'title' => 'Sales trend',
            'label' => 'This week · illustrative sample',
            'data' => [
                ['label' => 'Mon', 'total' => 690.1], ['label' => 'Tue', 'total' => 154.2],
                ['label' => 'Wed', 'total' => 875.3], ['label' => 'Thu', 'total' => 218.6],
                ['label' => 'Fri', 'total' => 1440.75], ['label' => 'Sat', 'total' => 708.4],
                ['label' => 'Sun', 'total' => 0],
            ],
        ],
        'month' => [
            'title' => 'Sales trend',
            'label' => 'This month · illustrative sample',
            'data' => [
                ['label' => 'Wk 1', 'total' => 3120.4], ['label' => 'Wk 2', 'total' => 2840.1],
                ['label' => 'Wk 3', 'total' => 4087.25], ['label' => 'Wk 4', 'total' => 3612.9],
            ],
        ],
        'year' => [
            'title' => 'Sales trend',
            'label' => 'This year · illustrative sample',
            'data' => [
                ['label' => 'Jan', 'total' => 9840.2], ['label' => 'Feb', 'total' => 8720.5],
                ['label' => 'Mar', 'total' => 10230.75], ['label' => 'Apr', 'total' => 9560.4],
                ['label' => 'May', 'total' => 11040.6], ['label' => 'Jun', 'total' => 10380.15],
                ['label' => 'Jul', 'total' => 12100.9], ['label' => 'Aug', 'total' => 13780.35],
                ['label' => 'Sep', 'total' => 0], ['label' => 'Oct', 'total' => 0],
                ['label' => 'Nov', 'total' => 0], ['label' => 'Dec', 'total' => 0],
            ],
        ],
    ];

    public function setSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIODS[$period])) {
            return;
        }

        $this->salesPeriod = $period;
        $this->dispatch('reports-chart-update', sales: self::SALES_PERIODS[$period]['data']);
    }

    public function updatedSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIODS[$period])) {
            $this->salesPeriod = 'week';
            $this->dispatch('reports-chart-update', sales: self::SALES_PERIODS['week']['data']);
        }
    }

    public function render()
    {
        $sales = session('demo.pos.employee.'.auth()->id().'.sales', []);
        $period = self::SALES_PERIODS[$this->salesPeriod];
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
