<section class="dashboard-page fcdc-dashboard" id="dashboard-page" data-dashboard-page>
    <header class="dashboard-heading"><div><h1>Dashboard</h1><p class="dashboard-subtitle">A live overview of inventory and financial performance.</p></div></header>
    <section class="dashboard-card dashboard-filters" aria-label="Dashboard date range">
        <div class="dashboard-period-toggle" role="group" aria-label="Date range">
            @foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year', 'custom' => 'Custom Date Range'] as $key => $label)
                <button type="button" class="dashboard-period-button {{ $period === $key ? 'active' : '' }}" wire:click="setPeriod('{{ $key }}')" aria-pressed="{{ $period === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        <label>From <input type="date" wire:model.live="fromDate" aria-label="Start date"></label>
        <label>To <input type="date" wire:model.live="toDate" aria-label="End date"></label>
    </section>
    <div class="dashboard-stats fcdc-dashboard-stats">
        <article class="dashboard-card dashboard-stat"><span class="dashboard-stat-icon">₱</span><div><p class="dashboard-stat-label">POS sales</p><p class="dashboard-stat-value">₱{{ number_format($salesTotal, 2) }}</p><p class="dashboard-stat-note">Completed sales in selected period</p></div></article>
        <article class="dashboard-card dashboard-stat"><span class="dashboard-stat-icon">▧</span><div><p class="dashboard-stat-label">Inventory value</p><p class="dashboard-stat-value">₱{{ number_format($inventoryValue, 2) }}</p><p class="dashboard-stat-note">{{ number_format($inventoryCount) }} tracked materials · current valuation</p></div></article>
        <article class="dashboard-card dashboard-stat"><span class="dashboard-stat-icon">↗</span><div><p class="dashboard-stat-label">Revenue</p><p class="dashboard-stat-value">₱{{ number_format($revenueTotal, 2) }}</p><p class="dashboard-stat-note">Posted approved accounting entries</p></div></article>
        <article class="dashboard-card dashboard-stat"><span class="dashboard-stat-icon warning">↘</span><div><p class="dashboard-stat-label">Expenses</p><p class="dashboard-stat-value">₱{{ number_format($expensesTotal, 2) }}</p><p class="dashboard-stat-note">Posted approved accounting entries</p></div></article>
        <article class="dashboard-card dashboard-stat"><span class="dashboard-stat-icon">%</span><div><p class="dashboard-stat-label">Output VAT</p><p class="dashboard-stat-value">₱{{ number_format($vatTotal, 2) }}</p><p class="dashboard-stat-note">Saved VAT records for completed POS sales</p></div></article>
    </div>
    <div class="fcdc-overview-grid">
        <div class="fcdc-overview-main">
            <section class="dashboard-card" aria-labelledby="stock-alerts-title"><div class="dashboard-card-heading"><h2 class="dashboard-card-title" id="stock-alerts-title">Stock alerts</h2><span class="fcdc-count">{{ $lowStockCount }}</span></div>
                @forelse ($stockAlerts as $item)<div class="dashboard-list-row" wire:key="dashboard-stock-alert-{{ $item->id }}"><div><strong>{{ $item->name }}</strong><span>{{ $item->category }} · {{ number_format((float) $item->qty, 2) }} {{ $item->unit }} on hand · low stock at {{ number_format((float) $item->reorder_level, 2) }}</span></div><span class="dashboard-stock-badge">{{ $item->stockStatusLabel() }}</span></div>@empty<p class="dashboard-empty">No low-stock items need attention.</p>@endforelse
                <p class="dashboard-chart-note">{{ $outOfStockCount }} items currently out of stock.</p>
            </section>
            <div class="fcdc-overview-charts">
                <section class="dashboard-card fcdc-chart-card" aria-labelledby="sales-performance-title"><h2 class="dashboard-card-title" id="sales-performance-title">Sales performance</h2><p class="dashboard-chart-note">Completed POS transaction totals</p><div class="dashboard-chart-wrap" data-dashboard-chart="sales" data-chart='@json($salesChart)'><canvas data-chart-canvas role="img" aria-label="POS sales by date"></canvas></div></section>
                <section class="dashboard-card fcdc-chart-card" aria-labelledby="inventory-performance-title"><h2 class="dashboard-card-title" id="inventory-performance-title">Inventory value by category</h2><p class="dashboard-chart-note">Current carrying value · positive-value categories</p>@if ($categoryValues === [])<p class="dashboard-empty">No inventory categories to chart yet.</p>@else<div class="fcdc-pie-layout"><div class="fcdc-pie-chart" data-dashboard-chart="pie" data-chart='@json($categoryValues)'><canvas data-chart-canvas role="img" aria-label="Donut chart of inventory carrying value by category"></canvas></div><ul class="fcdc-pie-legend">@foreach ($categoryValues as $index => $category)@if ($category['total'] > 0)<li><i style="--slice-color: {{ ['#245332', '#3f7d3a', '#79a96f', '#c9a227', '#8cae91', '#55735c'][$index % 6] }}"></i><span>{{ $category['label'] }}</span><strong>₱{{ number_format($category['total'], 2) }}</strong></li>@endif @endforeach</ul></div>@endif</section>
            </div>
        </div>
        <section class="dashboard-card fcdc-recent-card" aria-labelledby="recent-inventory-title"><div class="dashboard-card-heading"><h2 class="dashboard-card-title" id="recent-inventory-title">Recently added materials</h2><a href="{{ route('inventory.management') }}">View inventory</a></div>
            @forelse ($recentInventoryItems as $item)
                @php($imageUrl = $item->image ? (str_starts_with($item->image, 'uploads/') ? asset($item->image) : asset('storage/'.$item->image)) : null)
                <div class="fcdc-material-row" wire:key="dashboard-recent-inventory-{{ $item->id }}">
                    @if ($imageUrl)<img class="fcdc-material-image" src="{{ $imageUrl }}" alt="{{ $item->name }}" loading="lazy">@else<span class="fcdc-material-placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}</span>@endif
                    <div class="fcdc-material-details"><strong>{{ $item->name }}</strong><span>{{ $item->category }} · {{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</span></div>
                    <span class="fcdc-material-price">₱{{ number_format((float) $item->unit_cost, 2) }}<small>/ {{ $item->unit }}</small></span>
                </div>
            @empty<p class="dashboard-empty">No inventory materials have been added yet.</p>@endforelse
        </section>
    </div>
    <div class="dashboard-chart-grid fcdc-performance-grid">
        <section class="dashboard-card fcdc-chart-card" aria-labelledby="revenue-expenses-title"><h2 class="dashboard-card-title" id="revenue-expenses-title">Revenue vs. expenses</h2><p class="dashboard-chart-note">Posted accounting activity by approved classification</p><div class="dashboard-chart-wrap" data-dashboard-chart="comparison" data-chart='@json($accountingChart)'><canvas data-chart-canvas role="img" aria-label="Posted revenue compared with expenses"></canvas></div><div class="fcdc-chart-legend"><span><i class="revenue"></i>Revenue</span><span><i class="expenses"></i>Expenses</span></div></section>
        <section class="dashboard-card fcdc-chart-card" aria-labelledby="vat-performance-title"><h2 class="dashboard-card-title" id="vat-performance-title">VAT collected</h2><p class="dashboard-chart-note">Output VAT from persisted POS VAT records</p><div class="dashboard-chart-wrap" data-dashboard-chart="vat" data-chart='@json($vatChart)'><canvas data-chart-canvas role="img" aria-label="POS output VAT by date"></canvas></div></section>
    </div>
</section>
