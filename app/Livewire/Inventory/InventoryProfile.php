<?php

namespace App\Livewire\Inventory;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Component;

class InventoryProfile extends Component
{
    public int $inventoryId;

    public function mount(int $id): void
    {
        $this->inventoryId = $id;
    }

    public function render()
    {
        $item = Inventory::query()
            ->with(['batchCategory'])
            ->with(['stockMovements' => fn (HasMany $query) => $query
                ->whereIn('type', ['purchase_receipt', 'stock_in'])
                ->with('poster')
                ->orderByDesc('effective_date')
                ->orderByDesc('posted_at')])
            ->findOrFail($this->inventoryId);

        return view('livewire.inventory.inventory-profile', [
            'item' => $item,
            'receipts' => $item->stockMovements,
        ])->layout('layouts.app', ['title' => 'Inventory profile']);
    }

}
