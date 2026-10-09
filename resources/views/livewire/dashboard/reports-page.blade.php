<section class="dashboard-page reports-page" id="reports-page" data-reports-page>
    <header class="dashboard-heading">
        <div>
            <h1>Reports</h1>
            <p class="dashboard-subtitle">Sales performance and stock allocation.</p>
        </div>
    </header>

    <section class="dashboard-card reports-sales-card" aria-labelledby="completed-pos-sales-title">
        <div class="dashboard-card-heading">
            <h2 class="dashboard-card-title" id="completed-pos-sales-title">Completed POS sales</h2>
            <div class="dashboard-period-toggle" role="group" aria-label="POS sales reporting period">
                <button type="button" wire:click="$set('reportPeriod', 'daily')" @class(['active' => $reportPeriod === 'daily']) @if ($reportPeriod === 'daily') aria-pressed="true" @else aria-pressed="false" @endif>Daily</button>
                <button type="button" wire:click="$set('reportPeriod', 'monthly')" @class(['active' => $reportPeriod === 'monthly']) @if ($reportPeriod === 'monthly') aria-pressed="true" @else aria-pressed="false" @endif>Monthly</button>
            </div>
        </div>
        <label for="pos-report-date">{{ $reportPeriod === 'monthly' ? 'Month' : 'Day' }}</label>
        <input id="pos-report-date" type="date" wire:model.live="reportDate">
        @error('reportDate') <p class="reports-empty">{{ $message }}</p> @enderror
        <p class="dashboard-chart-note">
            Actual completed POS transactions · {{ $reportStart->format($reportPeriod === 'monthly' ? 'F Y' : 'F j, Y') }} ·
            {{ $posSales['count'] }} transactions
        </p>
        <p class="dashboard-stat-value">₱{{ number_format($posSales['total'], 2) }}</p>
        <p class="dashboard-stat-note">Recorded transaction totals, including recorded VAT. Illustrative sales below are separate.</p>

        <div class="reports-chart-grid">
            <section aria-labelledby="pos-sales-by-item-title">
                <h3 id="pos-sales-by-item-title">Sales by item</h3>
                <div class="reports-table-wrap">
                    <table class="reports-table">
                        <thead><tr><th>Item snapshot</th><th class="numeric">Quantity</th><th class="numeric">Sales</th></tr></thead>
                        <tbody>
                            @forelse ($posSales['items'] as $item)
                                <tr wire:key="pos-report-item-{{ $loop->index }}">
                                    <td>{{ $item->item_name }} · {{ $item->inventory_code }}</td>
                                    <td class="numeric">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                                    <td class="numeric">₱{{ number_format((float) $item->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="reports-empty">No completed POS transactions for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section aria-labelledby="pos-sales-by-category-title">
                <h3 id="pos-sales-by-category-title">Sales by category</h3>
                <div class="reports-table-wrap">
                    <table class="reports-table">
                        <thead><tr><th>Category snapshot</th><th class="numeric">Sales</th></tr></thead>
                        <tbody>
                            @forelse ($posSales['categories'] as $category)
                                <tr wire:key="pos-report-category-{{ $loop->index }}">
                                    <td>{{ $category->category }}</td>
                                    <td class="numeric">₱{{ number_format((float) $category->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="reports-empty">No completed POS transactions for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <h3>Sales by payment method</h3>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th>Payment method</th><th class="numeric">Transactions</th><th class="numeric">Sales</th></tr></thead>
                <tbody>
                    @forelse ($posSales['payments'] as $payment)
                        <tr wire:key="pos-report-payment-{{ $payment->payment_method }}">
                            <td>{{ str_replace('_', ' ', ucfirst($payment->payment_method)) }}</td>
                            <td class="numeric">{{ $payment->count }}</td>
                            <td class="numeric">₱{{ number_format((float) $payment->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="reports-empty">No completed POS transactions for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
