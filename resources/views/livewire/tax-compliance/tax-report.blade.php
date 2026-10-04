@php($money = fn ($amount) => '₱'.number_format($amount, 2))
<section class="tax-report-page" aria-labelledby="tax-report-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="tax-report-heading">Tax Report</h1>
            <p class="page-subtitle">Internal VAT demonstration report · illustrative placeholder values only</p>
        </div>
        <span class="vat-rate-badge">Illustrative 12% VAT</span>
    </header>

    <p class="temporary-notice" role="note">Demonstration only: this report is not evidence of actual taxable activity, is not a statutory tax return, and is not filed with a tax authority. POS demonstration sales are not included.</p>

    <div class="tax-report-controls">
        <label for="tax-report-period">Reporting period</label>
        <select id="tax-report-period" wire:model.live="period">
            @foreach ($periods as $availablePeriod)
                <option value="{{ $availablePeriod }}">{{ $availablePeriod }}</option>
            @endforeach
        </select>
        <button class="vat-detail-button" type="button" onclick="window.print()">Print report</button>
    </div>

    <article id="tax-report-document" class="tax-report-document" aria-label="Selected illustrative tax report">
        <header class="tax-report-document-header">
            <p class="tax-report-eyebrow">Internal report · illustrative values</p>
            <h2>VAT Tax Report</h2>
            <p>Reporting period: <strong>{{ $period }}</strong></p>
            <p>Generated: <time>{{ $generatedAt }}</time></p>
        </header>

        <p class="tax-report-disclaimer">Placeholder demonstration only. Not an official or statutory tax return; no information is submitted to a tax authority.</p>

        <section class="tax-report-summary" aria-label="Selected period totals">
            <div><span>Taxable sales</span><strong>{{ $money($summary['taxable']) }}</strong></div>
            <div><span>Illustrative VAT</span><strong>{{ $money($summary['vat']) }}</strong></div>
            <div><span>Total sales</span><strong>{{ $money($summary['total']) }}</strong></div>
        </section>

        <div class="vat-table-wrap tax-report-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transaction/reference no.</th>
                        <th>Transaction date</th>
                        <th>Tax period</th>
                        <th class="numeric">Taxable sales</th>
                        <th class="numeric">Illustrative VAT</th>
                        <th class="numeric">Total sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="tax-report-{{ $record['reference'] }}">
                            <td><strong>{{ $record['reference'] }}</strong></td>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['period'] }}</td>
                            <td class="numeric">{{ $money($record['taxable']) }}</td>
                            <td class="numeric">{{ $money($record['vat']) }}</td>
                            <td class="numeric"><strong>{{ $money($record['total']) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="6">No illustrative records exist for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</section>
