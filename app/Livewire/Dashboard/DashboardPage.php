<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use Livewire\Component;

class DashboardPage extends Component
{
    public function render()
    {
        $inventory = Inventory::query();
        $categoryValues = Inventory::query()
            ->selectRaw('category, SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)) as value')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn (Inventory $item) => [
                'label' => $item->category,
                'total' => (float) $item->value,
            ])
            ->all();

        return view('livewire.dashboard.dashboard-page', [
            'inventoryCount' => (clone $inventory)->count(),
            'inventoryValue' => (float) (clone $inventory)
                ->selectRaw('COALESCE(SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)), 0) as value')
                ->value('value'),
            'lowStockItems' => (clone $inventory)
                ->where('qty', '>', 0)
                ->whereColumn('qty', '<=', 'reorder_level')
                ->orderBy('name')
                ->get(),
            'outOfStockCount' => (clone $inventory)->where('qty', '<=', 0)->count(),
            'recentInventoryItems' => (clone $inventory)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'categoryValues' => $categoryValues,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
