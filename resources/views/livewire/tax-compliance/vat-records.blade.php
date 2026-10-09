@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
@php($manilaDate = fn ($date) => $date->copy()->timezone('Asia/Manila'))
<section class="vat-page" aria-labelledby="vat-records-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-records-heading">VAT Records</h1>
            <p class="page-subtitle">Recorded POS sales only · completion dates shown in Asia/Manila.</p>
        </div>
        <span class="vat-rate-badge">Standard-rated sales · 12% VAT</span>
    </header>

    <div class="vat-record-filters" aria-label="Filter recorded POS VAT records">
        <label>
            <span>Search transaction number</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search transaction number">
        </label>
        <label>
            <span>From (Manila date)</span>
            <input type="date" wire:model.live="startDate">
        </label>
        <label>
            <span>Through (Manila date)</span>
            <input type="date" wire:model.live="endDate">
        </label>
    </div>

    <section class="vat-record-card" aria-labelledby="vat-record-table-heading">
        <header>
            <div>
                <h2 id="vat-record-table-heading">Recorded POS sales</h2>
                <p>Amounts are copied from each completed sale. These records do not represent non-POS company activity.</p>
            </div>
            <span class="vat-record-count">{{ $recordCount }} recorded sales</span>
        </header>
        <div class="vat-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction no.</th>
                        <th>Manila completion</th>
                        <th class="numeric">Taxable sales</th>
                        <th>VAT rate</th>
                        <th class="numeric">Output VAT</th>
                        <th class="numeric">VAT-inclusive total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="vat-record-{{ $record->id }}">
                            <td><strong>{{ $record->posTransaction->transaction_number }}</strong></td>
                            <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                            <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                            <td>{{ number_format((float) $record->vat_rate * 100, 2) }}%</td>
                            <td class="numeric">{{ $money($record->output_vat) }}</td>
                            <td class="numeric"><strong>{{ $money($record->total) }}</strong></td>
                            <td><button class="vat-detail-button" type="button" wire:click="viewRecord({{ $record->id }})">View details</button></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="7">No recorded POS sales.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    </section>

    @if ($selectedRecord)
        @php($sale = $selectedRecord->posTransaction)
        <div class="vat-modal-backdrop" role="presentation" wire:click.self="closeDetails">
            <section class="vat-detail-modal" role="dialog" aria-modal="true" aria-labelledby="vat-detail-heading">
                <header>
                    <h2 id="vat-detail-heading">VAT Record Details</h2>
                    <button class="vat-modal-close" type="button" wire:click="closeDetails" aria-label="Close details">&times;</button>
                </header>
                <dl>
                    <div><dt>Transaction no.</dt><dd>{{ $sale->transaction_number }}</dd></div>
                    <div><dt>Completed (Asia/Manila)</dt><dd>{{ $manilaDate($selectedRecord->completed_at)->format('F j, Y') }} · {{ $manilaDate($selectedRecord->completed_at)->format('g:i A') }}</dd></div>
                    <div><dt>Customer</dt><dd>{{ $sale->customer_name ?: 'Walk-in customer' }}</dd></div>
                    <div><dt>Taxable sales</dt><dd>{{ $money($selectedRecord->taxable_sales) }}</dd></div>
                    <div><dt>VAT rate</dt><dd>{{ number_format((float) $selectedRecord->vat_rate * 100, 2) }}%</dd></div>
                    <div><dt>Output VAT</dt><dd>{{ $money($selectedRecord->output_vat) }}</dd></div>
                    <div><dt>VAT-inclusive total</dt><dd>{{ $money($selectedRecord->total) }}</dd></div>
                </dl>
                <h3>Saved sale items</h3>
                <div class="vat-table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Quantity</th><th class="numeric">Selling price</th><th class="numeric">Line taxable amount</th></tr></thead>
                        <tbody>
                            @foreach ($sale->lines as $line)
                                <tr wire:key="vat-line-{{ $line->id }}">
                                    <td>{{ $line->item_name }}</td>
                                    <td>{{ number_format((float) $line->quantity, 2) }} {{ $line->unit }}</td>
                                    <td class="numeric">{{ $money($line->selling_price) }}</td>
                                    <td class="numeric">{{ $money($line->line_subtotal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <footer><button class="vat-detail-button" type="button" wire:click="closeDetails">Close</button></footer>
            </section>
        </div>
    @endif
</section>
