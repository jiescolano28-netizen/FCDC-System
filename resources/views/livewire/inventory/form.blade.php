@if (auth()->user()->can('inventory.create') || auth()->user()->can('inventory.update'))
    <div class="panel inventory-form-panel">
        <form class="inventory-form" wire:submit="save">
            <fieldset class="inventory-form-section">
                <legend>Item details</legend>
                <div class="inventory-form-fields inventory-form-fields--identity">
                    <div class="inventory-form-field">
                        <label for="name">Material name</label>
                        <input id="name" wire:model="name">
                        @error('name')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="category">Category</label>
                        <input id="category" wire:model="category">
                        @error('category')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="sizeSpecification">Size / specification (optional)</label>
                        <input id="sizeSpecification" wire:model="sizeSpecification" maxlength="255" placeholder="e.g., 2 in × 4 in × 8 ft">
                        @error('sizeSpecification')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="batchCategoryId">Batch category (optional)</label>
                        <select id="batchCategoryId" wire:model="batchCategoryId">
                            <option value="">No batch category</option>
                            @foreach ($batchCategories as $batchCategory)
                                <option value="{{ $batchCategory->id }}">{{ $batchCategory->name }}{{ $batchCategory->trashed() ? ' (Deleted)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('batchCategoryId')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="unit">Unit label (e.g., pc, bag, kg)</label>
                        <input id="unit" wire:model="unit">
                        <span class="muted">This is the material’s unit name, not a quantity.</span>
                        @error('unit')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="inventory-form-section">
                <legend>Stock and pricing</legend>
                <div class="inventory-form-fields inventory-form-fields--stock">
                    <div class="inventory-form-field">
                        <label for="qty">Quantity{{ $editingId ? ' (on hand)' : '' }}</label>
                        @if ($editingId)
                            <input id="qty" type="number" min="0" step="1" wire:model="qty"
                                @readonly(!auth()->user()->can('inventory.movements.record'))>
                            <span class="muted">Saving a changed quantity records a stock adjustment.</span>
                        @else
                            <input id="qty" type="number" min="0" step="1" wire:model="qty">
                            <span class="muted">{{ $valuedPostingEnabled ? 'Starting on-hand quantity for this inventory item.' : 'How many are on hand at the start.' }}</span>
                            @error('qty')
                                <span class="error">{{ $message }}</span>
                            @enderror
                        @endif
                    </div>
                    <div class="inventory-form-field">
                        <label for="unitCost">Unit cost</label>
                        <input id="unitCost" type="number" min="0" step="0.01" wire:model="unitCost">
                        @error('unitCost')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="sellingPrice">Selling price for this batch (optional)</label>
                        <input id="sellingPrice" type="number" min="0" step="0.01" wire:model="sellingPrice">
                        @error('sellingPrice')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="reorderLevel">Low stock level</label>
                        <input id="reorderLevel" type="number" min="0" step="1" wire:model="reorderLevel">
                        @error('reorderLevel')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </fieldset>

            <fieldset class="inventory-form-section">
                <legend>Additional information</legend>
                <div class="inventory-form-fields inventory-form-fields--additional">
                    <div class="inventory-form-field">
                        <label for="description">Description (optional)</label>
                        <textarea id="description" wire:model="description"></textarea>
                        @error('description')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="image">Product image (optional)</label>
                        <input id="image" type="file" accept="image/jpeg,image/png,image/webp" wire:model="image">
                        @error('image')
                            <span class="error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </fieldset>

            <div class="actions inventory-form-actions">
                @if ($editingId)
                    @can('inventory.update')
                        <button class="primary" type="submit">Save changes</button>
                        <a class="secondary" href="{{ route('inventory.management') }}">Cancel</a>
                    @endcan
                @else
                    @can('inventory.create')
                        <button class="primary" type="submit">Add item</button>
                    @endcan
                @endif
            </div>
        </form>
    </div>
@endif
@if ($editingId)
    @include('livewire.inventory.stock-movements')
@endif
@if ($editingId)
    @include('livewire.inventory.stock-movements')
@endif
