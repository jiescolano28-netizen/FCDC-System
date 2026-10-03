<section>
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>Inventory management</h1>
            <p class="muted">Persisted stock records. Unit cost and customer selling price are separate values.</p>
        </div>
        <span>{{ $items->count() }} matching item(s)</span>
    </div>

    <div class="panel">
        <h2>{{ $editingId ? 'Edit inventory item' : 'Add inventory item' }}</h2>
        <form wire:submit="save">
            <div class="fields">
                <div><label for="name">Material name</label><input id="name" wire:model="name">@error('name') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="category">Category</label><input id="category" wire:model="category">@error('category') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="qty">Quantity</label><input id="qty" type="number" min="0" step="0.01" wire:model="qty">@error('qty') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="unit">Unit</label><input id="unit" wire:model="unit">@error('unit') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="unitCost">Unit cost</label><input id="unitCost" type="number" min="0" step="0.01" wire:model="unitCost">@error('unitCost') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="sellingPrice">Selling price (optional)</label><input id="sellingPrice" type="number" min="0" step="0.01" wire:model="sellingPrice">@error('sellingPrice') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="reorderLevel">Reorder level</label><input id="reorderLevel" type="number" min="0" step="0.01" wire:model="reorderLevel">@error('reorderLevel') <span class="error">{{ $message }}</span> @enderror</div>
                <div><label for="image">Product image</label><input id="image" type="file" accept="image/jpeg,image/png,image/webp" wire:model="image">@error('image') <span class="error">{{ $message }}</span> @enderror</div>
            </div>
            <div class="actions" style="margin-top:14px;">
                <button class="primary" type="submit">{{ $editingId ? 'Save changes' : 'Add item' }}</button>
                @if ($editingId)
                    <button class="secondary" type="button" wire:click="resetForm">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    <div class="toolbar panel">
        <div style="flex:1; min-width:220px;">
            <label for="search">Search inventory</label>
            <input id="search" type="search" placeholder="Name or category" wire:model.live.debounce.300ms="search">
        </div>
        <div style="min-width:190px;">
            <label for="categoryFilter">Category</label>
            <select id="categoryFilter" wire:model.live="categoryFilter">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr><th>Material</th><th>Category</th><th>Quantity</th><th>Unit cost</th><th>Selling price</th><th>Stock status</th><th>Sale status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="inventory-{{ $item->id }}">
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->qty }} {{ $item->unit }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td>{{ $item->selling_price === null ? '—' : number_format((float) $item->selling_price, 2) }}</td>
                        <td>
                            @if ((float) $item->qty <= 0)
                                <span class="status empty">Out of stock</span>
                            @elseif ((float) $item->qty <= (float) $item->reorder_level)
                                <span class="status warning">Reorder</span>
                            @else
                                <span class="status">In stock</span>
                            @endif
                        </td>
                        <td>
                            @if ($item->selling_price === null)
                                <span class="status warning">Not priced · unavailable for sale</span>
                            @else
                                <span class="status">Sale price set</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <button class="secondary" type="button" wire:click="edit({{ $item->id }})">Edit</button>
                                <button class="danger" type="button" wire:click="delete({{ $item->id }})" wire:confirm="Delete {{ $item->name }}?">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No inventory items match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
