<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>Inventory overview</h1>
            <p class="muted">Persisted material quantities, prices, inventory value, and stock status.</p>
        </div>
        <div class="columns" style="align-items:center;">
            <span>{{ $items->count() }} {{ $items->count() === 1 ? 'material' : 'materials' }}</span>
            <a class="primary-link" href="{{ route('inventory.management') }}">Manage inventory</a>
        </div>
    </div>

    <div class="toolbar panel">
        <div style="flex:1; min-width:220px;">
            <label for="overview-search">Search inventory</label>
            <input id="overview-search" type="search" placeholder="Name or category" wire:model.live.debounce.300ms="search">
        </div>
        <div style="min-width:190px;">
            <label for="overview-category">Category</label>
            <select id="overview-category" wire:model.live="categoryFilter">
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
                <tr>
                    <th>Material</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit cost</th>
                    <th>Selling price</th>
                    <th>Inventory value</th>
                    <th>Stock status</th>
                    <th>Last updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="overview-inventory-{{ $item->id }}">
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td>{{ $item->selling_price === null ? '—' : number_format((float) $item->selling_price, 2) }}</td>
                        <td>{{ number_format($item->carrying_value_cents === null ? (float) $item->qty * (float) $item->unit_cost : $item->carrying_value_cents / 100, 2) }}</td>
                        <td>
                            @if ((float) $item->qty <= 0)
                                <span class="status empty">Out of stock</span>
                            @elseif ((float) $item->qty <= (float) $item->reorder_level)
                                <span class="status warning">Reorder</span>
                            @else
                                <span class="status">In stock</span>
                            @endif
                        </td>
                        <td>{{ $item->updated_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No inventory items match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
