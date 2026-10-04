<?php

namespace App\Livewire\Inventory;

use App\Models\Inventory;
use Livewire\Component;

class InventoryOverview extends Component
{
    public string $search = '';

    public string $categoryFilter = '';

    public function render()
    {
        $items = Inventory::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('category', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.inventory.overview', [
            'items' => $items,
            'categories' => Inventory::query()->distinct()->orderBy('category')->pluck('category'),
        ])->layout('layouts.app', ['title' => 'Inventory Overview']);
    }
}
