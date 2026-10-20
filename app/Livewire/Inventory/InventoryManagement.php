<?php

namespace App\Livewire\Inventory;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\Inventory;
use App\Models\InventoryBatchCategory;
use App\Models\StockMovement;
use App\Services\Inventory\CorrectValuedStockMovement;
use App\Services\Inventory\RecordStockAdjustment;
use App\Services\Inventory\RecordStockIn;
use App\Services\Inventory\RecordStockOut;
use App\Services\Inventory\RecordValuedStockMovement;
use App\Services\Inventory\ReverseStockMovement;
use App\Support\AdministratorAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class InventoryManagement extends Component
{
    use WithFileUploads;

    public string $search = '';

    public string $categoryFilter = '';

    public bool $advancedFiltersOpen = false;

    public bool $showDeleted = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public bool $formPage = false;

    public string $name = '';

    public string $category = '';

    public string $sizeSpecification = '';

    public string $batchCategoryId = '';

    public string $qty = '';

    public string $unit = '';

    public string $unitCost = '';

    public string $sellingPrice = '';

    public string $reorderLevel = '';

    public string $description = '';

    #[Url(as: 'item')]
    public ?int $historyItemId = null;

    public string $stockQuantity = '';

    public string $stockReasonCategory = '';

    public string $stockNotes = '';

    public string $stockReference = '';

    public string $stockEffectiveDate = '';

    public string $issueQuantity = '';

    public string $issueReasonCategory = '';

    public string $issueNotes = '';

    public string $issueReference = '';

    public string $issueEffectiveDate = '';

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

    public function mount(?int $id = null): void
    {
        $today = now('Asia/Manila')->toDateString();
        $this->stockEffectiveDate = $today;
        $this->issueEffectiveDate = $today;
        $this->adjustmentEffectiveDate = $today;
        $this->formPage = request()->routeIs('inventory.management.create', 'inventory.management.edit') || $id !== null;

        if ($id !== null) {
            $this->edit($id);
        }
    }

    public function save(): void
    {
        $this->authorizePermission($this->editingId ? 'inventory.update' : 'inventory.create');
        $currentBatchCategoryId = $this->editingId
            ? Inventory::query()->whereKey($this->editingId)->value('inventory_batch_category_id')
            : null;
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'sizeSpecification' => ['nullable', 'string', 'max:255'],
            'batchCategoryId' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($currentBatchCategoryId): void {
                    if ($value === null) {
                        return;
                    }

                    $category = InventoryBatchCategory::withTrashed()->find($value);
                    if ($category && (! $category->trashed() || (int) $value === (int) $currentBatchCategoryId)) {
                        return;
                    }

                    $fail('Select an active batch category.');
                },
            ],
            'qty' => $this->editingId ? ['prohibited'] : ['required', 'numeric', 'min:0', 'multiple_of:1'],
            'unit' => ['required', 'string', 'max:50'],
            'unitCost' => ['required', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'reorderLevel' => ['nullable', 'numeric', 'min:0', 'multiple_of:1'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'qty.multiple_of' => 'The opening quantity must be a whole number.',
            'reorderLevel.multiple_of' => 'The low stock level must be a whole number.',
        ]);
        if ($this->valuedPostingEnabled() && ! $this->editingId && (float) $validated['qty'] !== 0.0) {
            throw ValidationException::withMessages(['qty' => 'New items after cutover must start at zero and receive stock through valued movements.']);
        }
        $item = $this->editingId ? Inventory::findOrFail($this->editingId) : new Inventory;
        $item->fill([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'size_specification' => $validated['sizeSpecification'] ?: null,
            'unit' => $validated['unit'],
            'unit_cost' => $this->valuedPostingEnabled()
                ? ($this->editingId ? $item->unit_cost : '0.00')
                : $validated['unitCost'],
            'selling_price' => $validated['sellingPrice'] === '' ? null : $validated['sellingPrice'],
            'reorder_level' => $validated['reorderLevel'] === '' ? 10 : $validated['reorderLevel'],
            'description' => $validated['description'] ?? null,
        ]);
        $item->inventory_batch_category_id = $validated['batchCategoryId'] ?: null;

        if (! $this->editingId) {
            $item->qty = $validated['qty'];
        }

        if ($this->image) {
            $item->image = $this->image->store('inventory', 'public');
        }

        DB::transaction(fn () => $item->save());
        $this->resetForm();
        $this->dispatch('inventory-saved');
        if ($this->formPage) {
            $this->redirectRoute('inventory.management');
        }

    }

    public function edit(int $id): void
    {
        abort_unless(
            auth()->user()?->can('inventory.update') || auth()->user()?->can('inventory.movements.record'),
            403,
        );
        $item = Inventory::findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->category = $item->category;
        $this->sizeSpecification = $item->size_specification ?? '';
        $this->batchCategoryId = (string) ($item->inventory_batch_category_id ?? '');
        $this->qty = '';
        $this->unit = $item->unit;
        $this->unitCost = $item->unit_cost;
        $this->sellingPrice = $item->selling_price ?? '';
        $this->reorderLevel = (string) (int) round((float) $item->reorder_level);
        $this->description = $item->description ?? '';
        $this->resetValidation();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->advancedFiltersOpen = ! $this->advancedFiltersOpen;
    }

    public function deactivate(int $id): void
    {
        $this->authorizeAdministrator();
        Inventory::findOrFail($id)->update(['status' => Inventory::STATUS_INACTIVE]);
        $this->resetForm();
    }

    public function reactivate(int $id): void
    {
        $this->authorizeAdministrator();
        Inventory::findOrFail($id)->update(['status' => Inventory::STATUS_ACTIVE]);
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizeAdministrator();
        Inventory::findOrFail($id)->delete();

        if ($this->historyItemId === $id) {
            $this->historyItemId = null;
        }

        $this->resetForm();
    }

    public function restore(int $id): void
    {
        $this->authorizeAdministrator();
        Inventory::onlyTrashed()->findOrFail($id)->restore();
        $this->resetForm();
    }

    public function updatedShowDeleted(): void
    {
        if (! AdministratorAccess::allows(auth()->user())) {
            $this->showDeleted = false;
        }
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
        app(CorrectValuedStockMovement::class)->correct(
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
        $itemId = $this->movementItemId();
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'issueQuantity' => ['required', 'numeric', 'gt:0', 'multiple_of:1'],
            'issueReasonCategory' => ['required', $valued ? 'in:project_use,damage_loss,count_correction,other' : 'in:purchase_receipt,project_use,sale,return,damage_loss,count_correction,other'],
            'issueNotes' => ['nullable', 'string', 'max:2000'],
            'issueReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'issueEffectiveDate' => ['required', 'date'],
        ], [
            'issueQuantity.multiple_of' => 'The quantity issued must be a whole number.',
        ]);

        if ($valued) {
            app(RecordValuedStockMovement::class)->handle(
                $itemId, 'stock_out', $validated['issueQuantity'],
                $validated['issueReasonCategory'], $validated['issueReference'],
                $validated['issueEffectiveDate'], (int) auth()->id(), null, $validated['issueNotes'] ?: null,
            );
        } else {
            app(RecordStockOut::class)->handle(
                $itemId, (float) $validated['issueQuantity'],
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
        $itemId = $this->movementItemId();
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'countedQuantity' => ['required', 'numeric', 'min:0', 'multiple_of:1'],
            'adjustmentReasonCategory' => ['required', 'in:project_use,damage_loss,count_correction,other'],
            'adjustmentNotes' => ['nullable', 'string', 'max:2000'],
            'adjustmentReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'adjustmentEffectiveDate' => ['required', 'date'],
            'adjustmentUnitValue' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ], [
            'countedQuantity.multiple_of' => 'The counted on-hand quantity must be a whole number.',
        ]);

        if ($valued) {
            app(RecordValuedStockMovement::class)->handle(
                $itemId, 'adjustment', $validated['countedQuantity'],
                $validated['adjustmentReasonCategory'], $validated['adjustmentReference'],
                $validated['adjustmentEffectiveDate'], (int) auth()->id(), $validated['adjustmentUnitValue'] ?: null, $validated['adjustmentNotes'] ?: null,
            );
        } else {
            app(RecordStockAdjustment::class)->handle(
                $itemId, (float) $validated['countedQuantity'],
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
        $itemId = $this->movementItemId();
        $valued = $this->valuedPostingEnabled();
        $validated = $this->validate([
            'stockQuantity' => ['required', 'numeric', 'gt:0', 'multiple_of:1'],
            'stockReasonCategory' => ['required', 'in:purchase_receipt,return,other'],
            'stockNotes' => ['nullable', 'string', 'max:2000'],
            'stockReference' => [$valued ? 'required' : 'nullable', 'string', 'max:255'],
            'stockEffectiveDate' => ['required', 'date'],
            'stockUnitValue' => [$valued ? 'required' : 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ], [
            'stockQuantity.multiple_of' => 'The quantity received must be a whole number.',
        ]);

        if ($valued) {
            $this->authorizePermission('inventory.valuation.approve');
            app(RecordValuedStockMovement::class)->handle(
                $itemId, 'stock_in', $validated['stockQuantity'],
                $validated['stockReasonCategory'], $validated['stockReference'],
                $validated['stockEffectiveDate'], (int) auth()->id(), $validated['stockUnitValue'], $validated['stockNotes'] ?: null,
            );
        } else {
            app(RecordStockIn::class)->handle(
                $itemId, (float) $validated['stockQuantity'],
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
        $this->reset(['editingId', 'name', 'category', 'sizeSpecification', 'batchCategoryId', 'qty', 'unit', 'unitCost', 'sellingPrice', 'reorderLevel', 'description', 'image']);
        $this->resetValidation();
    }

    public function render()
    {
        if ($this->formPage) {
            $movementItem = $this->editingId ? Inventory::findOrFail($this->editingId) : null;
            $batchCategories = InventoryBatchCategory::query()->orderBy('name')->get();
            $currentBatchCategory = $movementItem?->batchCategory;
            if ($currentBatchCategory?->trashed()) {
                $batchCategories->push($currentBatchCategory);
            }

            return view('livewire.inventory.form-page', [
                'valuedPostingEnabled' => $this->valuedPostingEnabled(),
                'batchCategories' => $batchCategories,
                'movementItem' => $movementItem,
            ])->layout('layouts.app', ['title' => $this->editingId ? 'Edit inventory item' : 'Add inventory item']);
        }

        $isAdministrator = AdministratorAccess::allows(auth()->user());
        $showDeleted = $this->showDeleted && $isAdministrator;
        $scope = $showDeleted ? Inventory::onlyTrashed() : Inventory::query();

        $items = $scope
            ->with(['batchCategory', 'stockMovements.poster', 'stockMovements.reversal', 'stockMovements.accountingJournal'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('category', 'like', '%'.$this->search.'%')
                        ->orWhere('size_specification', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category', $this->categoryFilter))
            ->orderByDesc('id')
            ->get();

        return view('livewire.inventory.management', [
            'items' => $items,
            'categories' => ($showDeleted ? Inventory::onlyTrashed() : Inventory::query())
                ->distinct()->orderBy('category')->pluck('category'),
            'batchCategories' => InventoryBatchCategory::query()->orderBy('name')->get(),
            'isAdministrator' => $isAdministrator,
            'showDeleted' => $showDeleted,

            'valuedPostingEnabled' => $this->valuedPostingEnabled(),
            'approvedExpenseAccounts' => AccountingAccount::query()->where('type', 'Expense')
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

    private function movementItemId(): int
    {
        abort_unless($this->formPage && $this->editingId !== null, 404);

        return $this->editingId;
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    private function authorizeAdministrator(): void
    {
        abort_unless(AdministratorAccess::allows(auth()->user()), 403);
    }

    private function valuedPostingEnabled(): bool
    {
        return AccountingJournal::query()
            ->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists();
    }
}
