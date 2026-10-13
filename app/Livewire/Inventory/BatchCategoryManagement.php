<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryBatchCategory;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BatchCategoryManagement extends Component
{
    public string $search = '';

    public bool $showDeleted = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public function create(): void
    {
        $this->authorizePermission('inventory.create');
        $this->resetForm();
        $this->dispatch('batch-category-modal-open');
    }

    public function edit(int $id): void
    {
        $this->authorizePermission('inventory.update');

        $category = InventoryBatchCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->resetValidation();
        $this->dispatch('batch-category-modal-open');
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category = $this->editingId
            ? InventoryBatchCategory::findOrFail($this->editingId)
            : new InventoryBatchCategory;
        $category->name = $validated['name'];
        $category->description = $validated['description'] ?? null;
        $category->updated_by = auth()->id();

        if (! $category->exists) {
            $category->created_by = auth()->id();
        }

        $category->save();
        $this->resetForm();
        $this->dispatch('batch-category-saved');
    }

    public function delete(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        InventoryBatchCategory::findOrFail($id)->delete();
        $this->resetForm();
    }

    public function restore(int $id): void
    {
        $this->authorizePermission('inventory.delete');

        InventoryBatchCategory::onlyTrashed()->findOrFail($id)->restore();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->resetValidation();
    }

    public function render()
    {
        $categories = ($this->showDeleted && auth()->user()->can('inventory.delete')
            ? InventoryBatchCategory::onlyTrashed()
            : InventoryBatchCategory::query())
            ->when(trim($this->search) !== '', function ($query): void {
                $search = trim($this->search);
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.inventory.batch-category-management', [
            'categories' => $categories,
        ])->layout('layouts.app', ['title' => 'Inventory batch categories']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
