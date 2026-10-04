@php($money = fn ($amount) => '₱'.number_format($amount, 2))
<section class="vat-page" aria-labelledby="vat-records-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-records-heading">VAT Records</h1>
            <p class="page-subtitle">Illustrative VAT-related records only. These are not actual sales, statutory tax records, or a filed return.</p>
        </div>
        <span class="vat-rate-badge">Illustrative 12% VAT</span>
    </header>

    <p class="temporary-notice" role="note">Demonstration only: these placeholder values are not evidence of taxable activity and do not constitute a statutory return. Nothing is filed with a tax authority. POS demonstration sales are not included.</p>

    <div class="vat-record-filters" aria-label="Filter VAT demonstration records">
        <label>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search by reference or tax period">
        </label>
        <label>
            <span>Tax period</span>
            <select wire:model.live="period">
                <option value="All">All tax periods</option>
                @foreach ($taxPeriods as $taxPeriod)
                    <option value="{{ $taxPeriod }}">{{ $taxPeriod }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Transaction date</span>
            <input type="date" wire:model.live="transactionDate">
        </label>
    </div>

    <section class="vat-record-card" aria-labelledby="vat-record-table-heading">
        <header>
            <div>
                <h2 id="vat-record-table-heading">VAT demonstration records</h2>
                <p>Illustrative fixtures for interface demonstration; no sales or tax records are created.</p>
            </div>
            <span class="vat-record-count">Showing {{ $records->count() }} of {{ $recordCount }} placeholder records</span>
        </header>
        <div class="vat-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction/reference no.</th>
                        <th>Transaction date</th>
                        <th>Tax period</th>
                        <th class="numeric">Taxable sales</th>
                        <th>VAT rate</th>
                        <th class="numeric">VAT amount</th>
                        <th class="numeric">Total sales</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="vat-record-{{ $record['reference'] }}">
                            <td><strong>{{ $record['reference'] }}</strong></td>
                            <td>{{ $record['date'] }}</td>
                            <td><span class="vat-period-badge">{{ $record['period'] }}</span></td>
                            <td class="numeric">{{ $money($record['taxable']) }}</td>
                            <td>12%</td>
                            <td class="numeric">{{ $money($record['vat']) }}</td>
                            <td class="numeric"><strong>{{ $money($record['total']) }}</strong></td>
                            <td><button class="vat-detail-button" type="button" wire:click="viewRecord('{{ $record['reference'] }}')">View details</button></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="8">No VAT records match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedRecord)
        <div class="vat-modal-backdrop" role="presentation" wire:click.self="closeDetails">
            <section class="vat-detail-modal" role="dialog" aria-modal="true" aria-labelledby="vat-detail-heading">
                <header>
                    <h2 id="vat-detail-heading">VAT Record Details</h2>
                    <button class="vat-modal-close" type="button" wire:click="closeDetails" aria-label="Close details">&times;</button>
                </header>
                <p class="vat-modal-note">Illustrative demonstration record; not an actual taxable transaction.</p>
                <dl>
                    <div><dt>Transaction/reference no.</dt><dd>{{ $selectedRecord['reference'] }}</dd></div>
                    <div><dt>Transaction date</dt><dd>{{ $selectedRecord['date'] }}</dd></div>
                    <div><dt>Tax period</dt><dd>{{ $selectedRecord['period'] }}</dd></div>
                    <div><dt>Taxable sales</dt><dd>{{ $money($selectedRecord['taxable']) }}</dd></div>
                    <div><dt>VAT rate</dt><dd>12% illustrative</dd></div>
                    <div><dt>VAT amount</dt><dd>{{ $money($selectedRecord['vat']) }}</dd></div>
                    <div><dt>Total sales</dt><dd>{{ $money($selectedRecord['total']) }}</dd></div>
                </dl>
                <footer><button class="vat-detail-button" type="button" wire:click="closeDetails">Close</button></footer>
            </section>
        </div>
    @endif
</section>
