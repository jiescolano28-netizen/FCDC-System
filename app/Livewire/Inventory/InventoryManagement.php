<?php

namespace App\Livewire\Inventory;

use App\Models\Inventory;
use Livewire\Component;
use Livewire\WithFileUploads;

class InventoryManagement extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $categoryFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $category = '';

    public string $qty = '';

    public string $unit = '';

    public string $unitCost = '';

    public string $sellingPrice = '';

    public string $reorderLevel = '';

    public $image;

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'reorderLevel' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $item = $this->editingId
            ? Inventory::findOrFail($this->editingId)
            : new Inventory;

        $item->fill([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'qty' => $validated['qty'],
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unitCost'],
            'selling_price' => $validated['sellingPrice'] === '' ? null : $validated['sellingPrice'],
            'reorder_level' => $validated['reorderLevel'] === '' ? 10 : $validated['reorderLevel'],
        ]);

        if ($this->image) {
            $item->image = $this->image->store('inventory', 'public');
        }

        $item->save();
        $this->resetForm();
        $this->dispatch('inventory-saved');
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $item = Inventory::findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->category = $item->category;
        $this->qty = $item->qty;
        $this->unit = $item->unit;
        $this->unitCost = $item->unit_cost;
        $this->sellingPrice = $item->selling_price ?? '';
        $this->reorderLevel = $item->reorder_level;
        $this->resetValidation();
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        Inventory::findOrFail($id)->delete();
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'category', 'qty', 'unit', 'unitCost', 'sellingPrice', 'reorderLevel', 'image']);
        $this->resetValidation();
    }

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

        return view('livewire.inventory.management', [
            'items' => $items,
            'categories' => Inventory::query()->distinct()->orderBy('category')->pluck('category'),
        ])->layout('layouts.app', ['title' => 'Inventory Management']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
