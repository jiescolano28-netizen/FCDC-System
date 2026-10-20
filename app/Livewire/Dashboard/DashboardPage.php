<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use Livewire\Component;

class DashboardPage extends Component
{
    public function render()
    {
        $inventory = Inventory::query();
        $inventoryItems = (clone $inventory)->orderBy('name')->get();
        $lowStockItems = $inventoryItems
            ->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_LOW_STOCK)
            ->values();
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
            'inventoryCount' => $inventoryItems->count(),
            'inventoryValue' => (float) (clone $inventory)
                ->selectRaw('COALESCE(SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)), 0) as value')
                ->value('value'),
            'lowStockCount' => $lowStockItems->count(),
            'stockAlerts' => $lowStockItems->sortBy('name')->values(),
            'outOfStockCount' => $inventoryItems
                ->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_OUT_OF_STOCK)
                ->count(),
            'recentInventoryItems' => (clone $inventory)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'categoryValues' => $categoryValues,
        ])->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
