<section class="dashboard-page" id="dashboard-page" data-dashboard-page>
    <header class="dashboard-heading">
        <div>
            <h1>Dashboard</h1>
            <p class="dashboard-subtitle">Persisted inventory overview.</p>
        </div>
    </header>

    <div class="dashboard-stats">
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▧</span>
            <div><p class="dashboard-stat-label">Inventory materials</p><p class="dashboard-stat-value">{{ $inventoryCount }}</p><p class="dashboard-stat-note">{{ $inventoryCount }} tracked {{ \Illuminate\Support\Str::plural('material', $inventoryCount) }}</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">₱</span>
            <div><p class="dashboard-stat-label">Inventory value</p><p class="dashboard-stat-value">₱{{ number_format($inventoryValue, 2) }}</p><p class="dashboard-stat-note">Quantity × unit cost</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon warning" aria-hidden="true">!</span>
            <div><p class="dashboard-stat-label">Low stock items</p><p class="dashboard-stat-value">{{ $lowStockItems->count() }}</p><p class="dashboard-stat-note">Available items at or below reorder level</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▥</span>
            <div><p class="dashboard-stat-label">Out of stock items</p><p class="dashboard-stat-value">{{ $outOfStockCount }}</p><p class="dashboard-stat-note">Items with zero or negative quantity</p></div>
        </article>
    </div>

    <div class="dashboard-chart-grid">
        <section class="dashboard-card" aria-labelledby="low-stock-title">
            <h2 class="dashboard-card-title" id="low-stock-title">Low stock alerts</h2>
            @forelse ($lowStockItems as $item)
                <div class="dashboard-list-row" wire:key="dashboard-low-stock-{{ $item->id }}">
                    <div><strong>{{ $item->name }}</strong><span>{{ $item->category }} · reorder at {{ number_format((float) $item->reorder_level, 2) }} {{ $item->unit }}</span></div>
                    <span class="dashboard-stock-badge">{{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</span>
                </div>
            @empty
                <p class="dashboard-empty">No low-stock items need reordering.</p>
            @endforelse
        </section>

        <section class="dashboard-card" aria-labelledby="recent-inventory-title">
            <h2 class="dashboard-card-title" id="recent-inventory-title">Recently added materials</h2>
            @forelse ($recentInventoryItems as $item)
                <div class="dashboard-list-row" wire:key="dashboard-recent-inventory-{{ $item->id }}">
                    <div><strong>{{ $item->name }}</strong><span>{{ $item->category }} · {{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</span></div>
                    <span class="dashboard-stock-badge">₱{{ number_format((float) $item->unit_cost, 2) }} / {{ $item->unit }}</span>
                </div>
            @empty
                <p class="dashboard-empty">No inventory materials have been added yet.</p>
            @endforelse
        </section>
    </div>

    <section class="dashboard-card dashboard-category-card" aria-labelledby="category-chart-title">
        <h2 class="dashboard-card-title" id="category-chart-title">Inventory value by category</h2>
        <p class="dashboard-chart-note">Persisted quantity × unit cost.</p>
        @if ($categoryValues === [])
            <p class="dashboard-empty">No inventory categories to chart yet.</p>
        @else
            <div class="dashboard-category-chart" style="height: {{ max(200, count($categoryValues) * 36) }}px" data-dashboard-chart="categories" data-chart='@json($categoryValues)'>
                <canvas data-chart-canvas role="img" aria-label="Persisted inventory value by category"></canvas>
            </div>
        @endif
    </section>
</section>
