<?php

namespace App\Livewire\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockAdjustment;
use App\Services\Inventory\RecordStockIn;
use App\Services\Inventory\RecordStockOut;
use App\Services\Inventory\ReverseStockMovement;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
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
    public string $description = '';
    public string $status = 'active';
    public ?int $historyItemId = null;
    public ?int $stockItemId = null;
    public string $stockQuantity = '';
    public string $stockReasonCategory = '';
    public string $stockNotes = '';
    public string $stockReference = '';
    public string $stockEffectiveDate = '';
    public ?int $issueItemId = null;
    public string $issueQuantity = '';
    public string $issueReasonCategory = '';
    public string $issueNotes = '';
    public string $issueReference = '';
    public string $issueEffectiveDate = '';
    public ?int $adjustmentItemId = null;
    public string $countedQuantity = '';
    public string $adjustmentReasonCategory = '';
    public string $adjustmentNotes = '';
    public string $adjustmentReference = '';
    public string $adjustmentEffectiveDate = '';
    public $image;
    public function mount(): void
    {
        $today = now()->toDateString();
        $this->stockEffectiveDate = $today;
        $this->issueEffectiveDate = $today;
        $this->adjustmentEffectiveDate = $today;
    }


    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'qty' => $this->editingId ? ['prohibited'] : ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'reorderLevel' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $item = $this->editingId ? Inventory::findOrFail($this->editingId) : new Inventory;
        $item->fill([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unitCost'],
            'selling_price' => $validated['sellingPrice'] === '' ? null : $validated['sellingPrice'],
            'reorder_level' => $validated['reorderLevel'] === '' ? 10 : $validated['reorderLevel'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);

        if (! $this->editingId) {
            $item->qty = $validated['qty'];
        }

        if ($this->image) {
            $item->image = $this->image->store('inventory', 'public');
        }

        DB::transaction(fn () => $item->save());
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
        $this->qty = '';
        $this->unit = $item->unit;
        $this->unitCost = $item->unit_cost;
        $this->sellingPrice = $item->selling_price ?? '';
        $this->reorderLevel = $item->reorder_level;
        $this->description = $item->description ?? '';
        $this->status = $item->status;
        $this->resetValidation();
    }

    public function deactivate(int $id): void
    {
        $this->authorizePermission('inventory.delete');
        Inventory::findOrFail($id)->update(['status' => 'inactive']);
        $this->resetForm();
    }

    public function showHistory(int $id): void
    {
        $this->historyItemId = $this->historyItemId === $id ? null : $id;
    }

    public function reverseStockMovement(int $movementId): void
    {
        $this->authorizePermission('inventory.movements.record');
        $movement = StockMovement::query()->findOrFail($movementId);
        if ($movement->recovered_material_id !== null) {
            throw ValidationException::withMessages(['stockMovement' => 'Recovered-material receipts must be corrected from the Demolition Projects recovery history.']);
        }
        app(ReverseStockMovement::class)->handle($movementId, (int) auth()->id());
    }

    public function recordStockOut(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $validated = $this->validate([
            'issueItemId' => ['required', 'integer', 'exists:inventories,id'],
            'issueQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'issueReasonCategory' => ['required', 'in:purchase_receipt,project_use,sale,return,damage_loss,count_correction,other'],
            'issueNotes' => ['nullable', 'string', 'max:2000'],
            'issueReference' => ['nullable', 'string', 'max:255'],
            'issueEffectiveDate' => ['required', 'date'],
        ]);

        app(RecordStockOut::class)->handle(
            (int) $validated['issueItemId'],
            (float) $validated['issueQuantity'],
            $validated['issueReasonCategory'],
            $validated['issueNotes'] ?: null,
            $validated['issueReference'] ?: null,
            $validated['issueEffectiveDate'],
            (int) auth()->id(),
        );

        $this->reset(['issueQuantity', 'issueReasonCategory', 'issueNotes', 'issueReference']);
        $this->issueEffectiveDate = now()->toDateString();
        $this->resetValidation();
    }

    public function recordStockAdjustment(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $validated = $this->validate([
            'adjustmentItemId' => ['required', 'integer', 'exists:inventories,id'],
            'countedQuantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'adjustmentReasonCategory' => ['required', 'in:purchase_receipt,project_use,sale,return,damage_loss,count_correction,other'],
            'adjustmentNotes' => ['nullable', 'string', 'max:2000'],
            'adjustmentReference' => ['nullable', 'string', 'max:255'],
            'adjustmentEffectiveDate' => ['required', 'date'],
        ]);

        app(RecordStockAdjustment::class)->handle(
            (int) $validated['adjustmentItemId'],
            (float) $validated['countedQuantity'],
            $validated['adjustmentReasonCategory'],
            $validated['adjustmentNotes'] ?: null,
            $validated['adjustmentReference'] ?: null,
            $validated['adjustmentEffectiveDate'],
            (int) auth()->id(),
        );

        $this->reset(['countedQuantity', 'adjustmentReasonCategory', 'adjustmentNotes', 'adjustmentReference']);
        $this->adjustmentEffectiveDate = now()->toDateString();
        $this->resetValidation();
    }

    public function recordStockIn(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $validated = $this->validate([
            'stockItemId' => ['required', 'integer', 'exists:inventories,id'],
            'stockQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'stockReasonCategory' => ['required', 'in:purchase_receipt,return,other'],
            'stockNotes' => ['nullable', 'string', 'max:2000'],
            'stockReference' => ['nullable', 'string', 'max:255'],
            'stockEffectiveDate' => ['required', 'date'],
        ]);

        app(RecordStockIn::class)->handle(
            (int) $validated['stockItemId'],
            (float) $validated['stockQuantity'],
            $validated['stockReasonCategory'],
            $validated['stockNotes'] ?: null,
            $validated['stockReference'] ?: null,
            $validated['stockEffectiveDate'],
            (int) auth()->id(),
        );

        $this->reset(['stockQuantity', 'stockReasonCategory', 'stockNotes', 'stockReference']);
        $this->stockEffectiveDate = now()->toDateString();
        $this->resetValidation();
    }


    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'category', 'qty', 'unit', 'unitCost', 'sellingPrice', 'reorderLevel', 'description', 'image']);
        $this->status = 'active';
        $this->resetValidation();
    }

    public function render()
    {
        $items = Inventory::query()
            ->with(['stockMovements.poster', 'stockMovements.reversal'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('category', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.inventory.management', [
            'items' => $items,
            'categories' => Inventory::query()->distinct()->orderBy('category')->pluck('category'),
            'activeItems' => Inventory::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
        ])->layout('layouts.app', ['title' => 'Inventory Management']);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
