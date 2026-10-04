<section class="dashboard-page" id="dashboard-page" data-dashboard-page>
    <header class="dashboard-heading">
        <div>
            <h1>Dashboard</h1>
            <p class="dashboard-subtitle">Persisted inventory overview and session-only demonstration activity.</p>
        </div>
    </header>

    <div class="dashboard-stats">
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▧</span>
            <div><p class="dashboard-stat-label">Inventory value</p><p class="dashboard-stat-value">₱{{ number_format($inventoryValue, 2) }}</p><p class="dashboard-stat-note">{{ $inventoryCount }} tracked {{ \Illuminate\Support\Str::plural('material', $inventoryCount) }}</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon warning" aria-hidden="true">!</span>
            <div><p class="dashboard-stat-label">Low stock items</p><p class="dashboard-stat-value">{{ $lowStockItems->count() }}</p><p class="dashboard-stat-note">{{ $lowStockItems->count() === 1 ? '1 item needs reordering' : 'Items at or below reorder level' }}</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">₱</span>
            <div><p class="dashboard-stat-label">Latest demo sale</p>
                @if (isset($sales[0]))
                    <p class="dashboard-stat-value">{{ $sales[0]['id'] }}</p><p class="dashboard-stat-note">{{ $sales[0]['date'] }} · {{ $sales[0]['items'] }} item(s)</p>
                @else
                    <p class="dashboard-stat-value">No demo sales yet</p><p class="dashboard-stat-note">Session-only activity</p>
                @endif
            </div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▥</span>
            <div><p class="dashboard-stat-label">{{ $period['title'] }}</p><p class="dashboard-stat-value">₱{{ number_format($periodTotal, 2) }}</p><p class="dashboard-stat-note">Illustrative sample · {{ $period['subLabel'] }}</p></div>
        </article>
    </div>

    <div class="dashboard-chart-grid">
        <section class="dashboard-card dashboard-sales-card" aria-labelledby="sales-chart-title">
            <div class="dashboard-card-heading">
                <h2 id="sales-chart-title">{{ $period['title'] }}</h2>
                <div class="dashboard-period-toggle" role="group" aria-label="Illustrative sales chart period">
                    @foreach (['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $key => $label)
                        <button type="button" wire:click="setSalesPeriod('{{ $key }}')" @class(['active' => $salesPeriod === $key]) @if ($salesPeriod === $key) aria-pressed="true" @else aria-pressed="false" @endif>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <p class="dashboard-chart-note">Sales chart uses illustrative sample data; it is not session sales or persisted reporting.</p>
            <div class="dashboard-chart-wrap" data-dashboard-chart="sales" data-chart='@json($period['data'])'>
                <canvas data-chart-canvas role="img" aria-label="Illustrative sales amounts for {{ strtolower($period['title']) }}"></canvas>
            </div>
        </section>

        <section class="dashboard-card" aria-labelledby="low-stock-title">
            <h2 class="dashboard-card-title" id="low-stock-title">Low stock alerts</h2>
            @forelse ($lowStockItems as $item)
                <div class="dashboard-list-row" wire:key="dashboard-low-stock-{{ $item->id }}">
                    <div><strong>{{ $item->name }}</strong><span>{{ $item->category }} · reorder at {{ number_format((float) $item->reorder_level, 2) }} {{ $item->unit }}</span></div>
                    <span class="dashboard-stock-badge">{{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</span>
                </div>
            @empty
                <p class="dashboard-empty">All inventory items are above their reorder levels.</p>
            @endforelse
        </section>
    </div>

    <section class="dashboard-card dashboard-category-card" aria-labelledby="category-chart-title">
        <h2 class="dashboard-card-title" id="category-chart-title">Inventory value by category</h2>
        <p class="dashboard-chart-note">Persisted quantity × unit cost; demonstration checkout does not change these values.</p>
        @if ($categoryValues === [])
            <p class="dashboard-empty">No inventory categories to chart yet.</p>
        @else
            <div class="dashboard-category-chart" style="height: {{ max(200, count($categoryValues) * 36) }}px" data-dashboard-chart="categories" data-chart='@json($categoryValues)'>
                <canvas data-chart-canvas role="img" aria-label="Persisted inventory value by category"></canvas>
            </div>
        @endif
    </section>

    @include('partials.demo-sales', ['sales' => $sales])
</section>
