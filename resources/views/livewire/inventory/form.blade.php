@if (auth()->user()->can('inventory.create') || auth()->user()->can('inventory.update'))
    <div class="panel">
        <form wire:submit="save">
            <div class="fields">
                <div><label for="name">Material name</label><input id="name" wire:model="name">@error('name') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="category">Category</label><input id="category" wire:model="category">@error('category') <span class="error">{{ $message }}</span> @enderror</div>
                @if (! $editingId)
                    <div><label for="qty">Opening quantity</label><input id="qty" type="number" min="0" step="0.01" wire:model="qty">@error('qty') <span class="error">{{ $message }}</span> @enderror</div>
                @endif
                <div><label for="unit">Unit</label><input id="unit" wire:model="unit">@error('unit') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="unitCost">Unit cost{{ $valuedPostingEnabled ? ' · from valued stock schedule' : '' }}</label><input id="unitCost" type="number" min="0" step="0.01" wire:model="unitCost" @readonly($valuedPostingEnabled)>@error('unitCost') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="sellingPrice">Selling price (optional)</label><input id="sellingPrice" type="number" min="0" step="0.01" wire:model="sellingPrice">@error('sellingPrice') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="reorderLevel">Reorder level</label><input id="reorderLevel" type="number" min="0" step="0.01" wire:model="reorderLevel">@error('reorderLevel') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="description">Description (optional)</label><textarea id="description" wire:model="description"></textarea>@error('description') <span class="error">{{ $message }}</span> @enderror</div>
                @if ($editingId)
                    <div><label for="status">Lifecycle status</label><select id="status" wire:model="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>@error('status') <span class="error">{{ $message }}</span> @enderror</div>
                @endif
                <div><label for="image">Product image (optional)</label><input id="image" type="file" accept="image/jpeg,image/png,image/webp" wire:model="image">@error('image') <span class="error">{{ $message }}</span> @enderror</div>
            </div>
            <div class="actions" style="margin-top:14px;">
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
