<?php

namespace App\Livewire\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Inventory\RecordStockAdjustment;
use App\Services\Inventory\RecordStockIn;
use App\Services\Inventory\RecordStockOut;
use App\Services\Inventory\ReverseStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
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

    #[Url(as: 'item')]
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
    public string $stockUnitValue = '';
    public string $adjustmentUnitValue = '';
    public ?int $correctionMovementId = null;
    public string $correctionReason = '';
    public string $remainingValueDelta = '0.00';
    public string $consumedValueDelta = '0.00';
    public ?int $consumedExpenseAccountId = null;
    public $image;
    public function mount(): void
    {
        $today = now('Asia/Manila')->toDateString();
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

        if ($this->valuedPostingEnabled() && ! $this->editingId && (float) $validated['qty'] !== 0.0) {
            throw ValidationException::withMessages(['qty' => 'New items after cutover must start at zero and receive stock through valued movements.']);
        }
        $item = $this->editingId ? Inventory::findOrFail($this->editingId) : new Inventory;
        $item->fill([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit' => $validated['unit'],
            'unit_cost' => $this->valuedPostingEnabled()
                ? ($this->editingId ? $item->unit_cost : '0.00')
                : $validated['unitCost'],
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

    public function beginValuationCorrection(int $movementId): void
    {
        $this->authorizePermission('inventory.movements.record');
        $this->authorizePermission('inventory.valuation.approve');
        $movement = StockMovement::query()->findOrFail($movementId);
        if ($movement->value_cents === null || $movement->value_cents <= 0 || $movement->quantity <= 0
            || $movement->correction_of_movement_id !== null) {
            throw ValidationException::withMessages(['movement' => 'Only an original valued receipt can be corrected.']);
        }
        $this->correctionMovementId = $movement->id;
        $this->resetValidation();
    }

    public function saveValuationCorrection(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $this->authorizePermission('inventory.valuation.approve');
        $validated = $this->validate([
            'correctionMovementId' => ['required', 'integer', 'exists:stock_movements,id'],
            'correctionReason' => ['required', 'string', 'max:4000'],
            'remainingValueDelta' => ['required', 'numeric', 'decimal:0,2'],
            'consumedValueDelta' => ['required', 'numeric', 'decimal:0,2'],
            'consumedExpenseAccountId' => ['nullable', 'integer', 'exists:accounting_accounts,id'],
        ]);
        app(\App\Services\Inventory\CorrectValuedStockMovement::class)->correct(
            (int) $validated['correctionMovementId'],
            $validated['correctionReason'],
            $this->amountToCents($validated['remainingValueDelta']),
            $this->amountToCents($validated['consumedValueDelta']),
            $validated['consumedExpenseAccountId'] ? (int) $validated['consumedExpenseAccountId'] : null,
            (int) auth()->id(),
        );
        $this->reset(['correctionMovementId', 'correctionReason', 'remainingValueDelta', 'consumedValueDelta', 'consumedExpenseAccountId']);
    }

    public function cancelValuationCorrection(): void
    {
        $this->reset(['correctionMovementId', 'correctionReason', 'remainingValueDelta', 'consumedValueDelta', 'consumedExpenseAccountId']);
        $this->resetValidation();
    }

    public function recordStockOut(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'issueItemId' => ['required', 'integer', 'exists:inventories,id'],
            'issueQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'issueReasonCategory' => ['required', $valued ? 'in:project_use,damage_loss,count_correction,other' : 'in:purchase_receipt,project_use,sale,return,damage_loss,count_correction,other'],
            'issueNotes' => ['nullable', 'string', 'max:2000'],
            'issueReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'issueEffectiveDate' => ['required', 'date'],
        ]);

        if ($valued) {
            app(\App\Services\Inventory\RecordValuedStockMovement::class)->handle(
                (int) $validated['issueItemId'], 'stock_out', $validated['issueQuantity'],
                $validated['issueReasonCategory'], $validated['issueReference'],
                $validated['issueEffectiveDate'], (int) auth()->id(), null, $validated['issueNotes'] ?: null,
            );
        } else {
            app(RecordStockOut::class)->handle(
                (int) $validated['issueItemId'], (float) $validated['issueQuantity'],
                $validated['issueReasonCategory'], $validated['issueNotes'] ?: null,
                $validated['issueReference'] ?: null, $validated['issueEffectiveDate'], (int) auth()->id(),
            );
        }

        $this->reset(['issueQuantity', 'issueReasonCategory', 'issueNotes', 'issueReference']);
        $this->issueEffectiveDate = now('Asia/Manila')->toDateString();
        $this->resetValidation();
    }

    public function recordStockAdjustment(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'adjustmentItemId' => ['required', 'integer', 'exists:inventories,id'],
            'countedQuantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'adjustmentReasonCategory' => ['required', 'in:project_use,damage_loss,count_correction,other'],
            'adjustmentNotes' => ['nullable', 'string', 'max:2000'],
            'adjustmentReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'adjustmentEffectiveDate' => ['required', 'date'],
            'adjustmentUnitValue' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        if ($valued) {
            app(\App\Services\Inventory\RecordValuedStockMovement::class)->handle(
                (int) $validated['adjustmentItemId'], 'adjustment', $validated['countedQuantity'],
                $validated['adjustmentReasonCategory'], $validated['adjustmentReference'],
                $validated['adjustmentEffectiveDate'], (int) auth()->id(), $validated['adjustmentUnitValue'] ?: null, $validated['adjustmentNotes'] ?: null,
            );
        } else {
            app(RecordStockAdjustment::class)->handle(
                (int) $validated['adjustmentItemId'], (float) $validated['countedQuantity'],
                $validated['adjustmentReasonCategory'], $validated['adjustmentNotes'] ?: null,
                $validated['adjustmentReference'] ?: null, $validated['adjustmentEffectiveDate'], (int) auth()->id(),
            );
        }

        $this->reset(['countedQuantity', 'adjustmentReasonCategory', 'adjustmentNotes', 'adjustmentReference', 'adjustmentUnitValue']);
        $this->adjustmentEffectiveDate = now('Asia/Manila')->toDateString();
        $this->resetValidation();
    }

    public function recordStockIn(): void
    {
        $this->authorizePermission('inventory.movements.record');
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'stockItemId' => ['required', 'integer', 'exists:inventories,id'],
            'stockQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'stockReasonCategory' => ['required', 'in:purchase_receipt,return,other'],
            'stockNotes' => ['nullable', 'string', 'max:2000'],
            'stockReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'stockEffectiveDate' => ['required', 'date'],
            'stockUnitValue' => [$valued ? 'required' : 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        if ($valued) {
            $this->authorizePermission('inventory.valuation.approve');
            app(\App\Services\Inventory\RecordValuedStockMovement::class)->handle(
                (int) $validated['stockItemId'], 'stock_in', $validated['stockQuantity'],
                $validated['stockReasonCategory'], $validated['stockReference'],
                $validated['stockEffectiveDate'], (int) auth()->id(), $validated['stockUnitValue'], $validated['stockNotes'] ?: null,
            );
        } else {
            app(RecordStockIn::class)->handle(
                (int) $validated['stockItemId'], (float) $validated['stockQuantity'],
                $validated['stockReasonCategory'], $validated['stockNotes'] ?: null,
                $validated['stockReference'] ?: null, $validated['stockEffectiveDate'], (int) auth()->id(),
            );
        }

        $this->reset(['stockQuantity', 'stockReasonCategory', 'stockNotes', 'stockReference', 'stockUnitValue']);
        $this->stockEffectiveDate = now('Asia/Manila')->toDateString();
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
            ->with(['stockMovements.poster', 'stockMovements.reversal', 'stockMovements.accountingJournal'])
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
            'valuedPostingEnabled' => $this->valuedPostingEnabled(),
            'approvedExpenseAccounts' => \App\Models\AccountingAccount::query()->where('type', 'Expense')
                ->where('is_active', true)->whereNotNull('approved_at')->orderBy('code')->get(),
        ])->layout('layouts.app', ['title' => 'Inventory Management']);
    }

    private function amountToCents(string $amount): int
    {
        if (! preg_match('/^(-?)(\\d+)(?:\\.(\\d{1,2}))?$/', trim($amount), $parts)) {
            throw ValidationException::withMessages(['valuation' => 'Enter a monetary amount with no more than two decimal places.']);
        }
        $whole = (int) $parts[2];
        if ($whole > intdiv(PHP_INT_MAX - 99, 100)) {
            throw ValidationException::withMessages(['valuation' => 'The monetary amount exceeds the supported centavo range.']);
        }
        $cents = $whole * 100 + (int) str_pad($parts[3] ?? '', 2, '0');

        return ($parts[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
    private function valuedPostingEnabled(): bool
    {
        return \App\Models\AccountingJournal::query()
            ->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists();
    }

}
