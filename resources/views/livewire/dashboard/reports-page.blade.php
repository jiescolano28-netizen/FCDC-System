<section class="dashboard-page reports-page" id="reports-page" data-reports-page>
    <header class="dashboard-heading">
        <div>
            <h1>Reports</h1>
            <p class="dashboard-subtitle">Sales performance and stock allocation.</p>
        </div>
    </header>

    <div class="dashboard-stats reports-stats">
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">₱</span>
            <div><p class="dashboard-stat-label">Illustrative sales</p><p class="dashboard-stat-value">₱{{ number_format($periodTotal, 2) }}</p><p class="dashboard-stat-note">{{ $period['label'] }} · not recorded sales</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▧</span>
            <div><p class="dashboard-stat-label">Inventory value</p><p class="dashboard-stat-value">₱{{ number_format($inventoryValue, 2) }}</p><p class="dashboard-stat-note">Persisted Inventory records</p></div>
        </article>
        <article class="dashboard-card dashboard-stat">
            <span class="dashboard-stat-icon" aria-hidden="true">▥</span>
            <div><p class="dashboard-stat-label">Tracked inventory items</p><p class="dashboard-stat-value">{{ $inventoryCount }}</p><p class="dashboard-stat-note">Persisted Inventory records</p></div>
        </article>
    </div>

    <div class="reports-chart-grid">
        <section class="dashboard-card reports-chart-card" aria-labelledby="reports-sales-title">
            <div class="dashboard-card-heading">
                <h2 id="reports-sales-title">Sales trend</h2>
                <div class="dashboard-period-toggle" role="group" aria-label="Illustrative sales trend period">
                    @foreach (['week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $key => $label)
                        <button type="button" wire:click="setSalesPeriod('{{ $key }}')" @class(['active' => $salesPeriod === $key]) @if ($salesPeriod === $key) aria-pressed="true" @else aria-pressed="false" @endif>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <p class="dashboard-chart-note">Fixed illustrative series, separate from completed POS transactions and persisted inventory.</p>
            <div class="dashboard-chart-wrap" data-dashboard-chart="sales" data-chart='@json($period['data'])'>
                <canvas data-chart-canvas role="img" aria-label="Illustrative sales trend for {{ $period['label'] }}"></canvas>
            </div>
        </section>

        <section class="dashboard-card" aria-labelledby="reports-category-title">
            <h2 class="dashboard-card-title" id="reports-category-title">Inventory value by category</h2>
            <p class="dashboard-chart-note">Persisted quantity × unit cost. Completed sales update on-hand quantities.</p>
            @if ($categoryValues === [])
                <p class="dashboard-empty">No inventory categories to chart yet.</p>
            @else
                <div class="dashboard-category-chart reports-category-chart" style="height: {{ max(200, count($categoryValues) * 36) }}px" data-dashboard-chart="categories" data-chart='@json($categoryValues)'>
                    <canvas data-chart-canvas role="img" aria-label="Persisted inventory value and quantity by category"></canvas>
                </div>
                <ul class="reports-category-quantities" aria-label="Persisted inventory quantities by category and unit">
                    @foreach ($inventoryQuantities as $inventoryQuantity)
                        <li>{{ $inventoryQuantity->category }} · {{ $inventoryQuantity->unit }}: {{ number_format((float) $inventoryQuantity->quantity, 2) }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="dashboard-card reports-sales-card" aria-labelledby="recent-sales-title">
        <h2 class="dashboard-card-title" id="recent-sales-title">Recent sales</h2>
        <p class="dashboard-chart-note">Completed POS transactions recorded at checkout. The chart above remains illustrative.</p>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th>Transaction</th><th>Date</th><th class="numeric">Items</th><th class="numeric">Total</th><th>Method</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr wire:key="reports-transaction-{{ $sale->id }}">
                            <td>{{ $sale->transaction_number }}</td><td>{{ $sale->completed_at->format('Y-m-d') }}</td><td class="numeric">{{ number_format((float) $sale->lines->sum('quantity'), 2) }}</td><td class="numeric">₱{{ number_format((float) $sale->total, 2) }}</td><td>{{ str_replace('_', ' ', ucfirst($sale->payment_method)) }}</td><td>{{ ucfirst($sale->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="reports-empty">No completed transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
