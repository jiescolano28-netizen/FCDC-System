<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;

class DashboardPage extends Component
{
    public string $salesPeriod = 'week';

    private const SALES_PERIODS = [
        'week' => ['title' => 'Demo sales this week', 'subLabel' => 'Mon–Sun'],
        'month' => ['title' => 'Demo sales this month', 'subLabel' => 'By week'],
        'year' => ['title' => 'Demo sales this year', 'subLabel' => 'Jan–Dec'],
    ];

    public function setSalesPeriod(string $period): void
    {
        if (! isset(self::SALES_PERIODS[$period])) {
            return;
        }

        $this->salesPeriod = $period;
        $this->dispatch('dashboard-chart-update', sales: $this->salesChartData($period));
    }

    public function updatedSalesPeriod(string $period): void
    {
        if (isset(self::SALES_PERIODS[$period])) {
            return;
        }

        $this->salesPeriod = 'week';
        $this->dispatch('dashboard-chart-update', sales: $this->salesChartData('week'));
    }

    public function render()
    {
        $period = self::SALES_PERIODS[$this->salesPeriod] + [
            'data' => $this->salesChartData($this->salesPeriod),
        ];
        $sales = session('demo.pos.employee.'.auth()->id().'.sales', []);
        $latestInventoryItem = Inventory::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
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
            'latestInventoryItem' => $latestInventoryItem,
            'sales' => $sales,
            'period' => $period,
            'periodTotal' => array_sum(array_column($period['data'], 'total')),
            'categoryValues' => $categoryValues,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }

    private function salesChartData(string $period): array
    {
        $now = CarbonImmutable::now();
        $sales = collect(session('demo.pos.employee.'.auth()->id().'.sales', []))
            ->filter(fn ($sale) => isset($sale['date'], $sale['total']) && is_numeric($sale['total']));

        if ($period === 'week') {
            $start = $now->startOfWeek();
            $buckets = collect(range(0, 6))->mapWithKeys(
                fn (int $offset) => [$start->addDays($offset)->toDateString() => [
                    'label' => $start->addDays($offset)->format('D'),
                    'total' => 0.0,
                ]]
            );

            return $this->sumSalesIntoBuckets($sales, $buckets, fn (CarbonImmutable $date) => $date->toDateString());
        }

        if ($period === 'month') {
            $buckets = collect(range(1, 5))->mapWithKeys(
                fn (int $week) => [$week => ['label' => 'Wk '.$week, 'total' => 0.0]]
            );

            return $this->sumSalesIntoBuckets(
                $sales,
                $buckets,
                fn (CarbonImmutable $date) => (int) ceil($date->day / 7),
                fn (CarbonImmutable $date) => $date->year === $now->year && $date->month === $now->month
            );
        }

        $buckets = collect(range(1, 12))->mapWithKeys(
            fn (int $month) => [$month => [
                'label' => CarbonImmutable::create($now->year, $month, 1)->format('M'),
                'total' => 0.0,
            ]]
        );

        return $this->sumSalesIntoBuckets(
            $sales,
            $buckets,
            fn (CarbonImmutable $date) => $date->month,
            fn (CarbonImmutable $date) => $date->year === $now->year
        );
    }

    private function sumSalesIntoBuckets(Collection $sales, Collection $buckets, callable $bucketFor, ?callable $include = null): array
    {
        foreach ($sales as $sale) {
            try {
                $date = CarbonImmutable::parse($sale['date']);
            } catch (\Throwable) {
                continue;
            }

            if ($include !== null && ! $include($date)) {
                continue;
            }

            $key = $bucketFor($date);
            if (isset($buckets[$key])) {
                $bucket = $buckets->get($key);
                $bucket['total'] += (float) $sale['total'];
                $buckets->put($key, $bucket);
            }
        }

        return $buckets->values()->all();
    }
}
