<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>{{ $item->name }}</h1>
            <p class="muted">{{ $item->code }} · {{ $item->category }}</p>
        </div>
        <div class="actions">
            <a class="secondary" href="{{ route('inventory.management') }}">Back to inventory</a>
            @if (auth()->user()->can('inventory.update'))
                <a class="primary" href="{{ route('inventory.management.edit', $item->id) }}">Edit item</a>
            @elseif (auth()->user()->can('inventory.movements.record'))
                <a class="primary" href="{{ route('inventory.management.edit', $item->id) }}">Record stock movement</a>
            @endif
        </div>
    </div>

    <section class="panel" aria-labelledby="inventory-details-heading">
        <h2 id="inventory-details-heading">Details</h2>
        <dl class="inventory-detail-grid">
            <div><dt>Code</dt><dd>{{ $item->code }}</dd></div>
            <div><dt>Category</dt><dd>{{ $item->category }}</dd></div>
            <div><dt>Batch category</dt><dd>{{ $item->batchCategory?->name ?? '—' }}</dd></div>
            <div><dt>Quantity</dt><dd>{{ number_format((float) $item->qty, 2) }} {{ $item->unit }}</dd></div>
            <div><dt>Unit cost (PHP)</dt><dd>{{ number_format((float) $item->unit_cost, 2) }}</dd></div>
            <div><dt>Selling price (PHP)</dt><dd>{{ $item->selling_price === null ? '—' : number_format((float) $item->selling_price, 2) }}</dd></div>
            <div><dt>Reorder level</dt><dd>{{ number_format((float) $item->reorder_level, 2) }} {{ $item->unit }}</dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($item->status) }}</dd></div>
            <div><dt>Description</dt><dd>{{ $item->description ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="panel receipt-panel" aria-labelledby="inventory-receipts-heading">
        <header class="receipt-heading">
            <div>
                <h2 id="inventory-receipts-heading">Receipts</h2>
                <p>Review receipt dates, quantities, and posting details for this item.</p>
            </div>
        </header>
        <div class="receipt-table-scroll" role="region" aria-label="Inventory receipts" tabindex="0">
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Receipt</th>
                        <th scope="col">Reason</th>
                        <th scope="col" class="numeric">Quantity</th>
                        <th scope="col" class="numeric">Value (PHP)</th>
                        <th scope="col">Posted by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                    <tr wire:key="inventory-receipt-{{ $receipt->id }}">
                        <td class="receipt-date">{{ $receipt->effective_date->format('Y-m-d') }}</td>
                        <td>{{ $receipt->reference ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $receipt->reason_category)) }}</td>
                        <td class="numeric">{{ number_format((float) $receipt->quantity, 2) }} {{ $item->unit }}</td>
                        <td class="numeric">{{ $receipt->value_cents === null ? '—' : number_format($receipt->value_cents / 100, 2) }}</td>
                        <td>{{ $receipt->poster?->username ?? 'System migration' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="receipt-empty">No receipts recorded for this item.</td></tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </section>
</section>
