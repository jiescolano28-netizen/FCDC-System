@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
@php($manilaDate = fn ($date) => $date->copy()->timezone('Asia/Manila'))
<section class="tax-report-page" aria-labelledby="tax-report-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="tax-report-heading">Tax Report</h1>
            <p class="page-subtitle">Recorded POS sales only · completion dates shown in Asia/Manila.</p>
        </div>
        <span class="vat-rate-badge">Internal reporting aid</span>
    </header>

    <div class="tax-report-controls" aria-label="Select report period">
        <label for="tax-report-type">Period type</label>
        <select id="tax-report-type" wire:model.live="periodType">
            <option value="monthly">Month</option>
            <option value="quarterly">Calendar quarter</option>
            <option value="custom">Custom date range</option>
        </select>
        @if ($periodType !== 'custom')
            <label for="tax-report-year">Year</label>
            <select id="tax-report-year" wire:model.live="selectedYear">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
            <label for="tax-report-period">{{ $periodType === 'quarterly' ? 'Quarter' : 'Month' }}</label>
            <select id="tax-report-period" wire:model.live="selectedPeriod">
                @foreach ($periods as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                @endforeach
            </select>
        @else
            <label for="tax-report-start">From (Manila date)</label>
            <input id="tax-report-start" type="date" wire:model.live="startDate">
            <label for="tax-report-end">Through (Manila date)</label>
            <input id="tax-report-end" type="date" wire:model.live="endDate">
        @endif
        <a class="vat-detail-button tax-report-export" href="#" wire:click.prevent="exportCsv">Download CSV</a>
        <button class="vat-detail-button tax-report-print" type="button" onclick="window.print()">Print report</button>
    </div>

    <article id="tax-report-document" class="tax-report-document" aria-label="Selected POS-sourced VAT report">
        <header class="tax-report-document-header">
            <p class="tax-report-eyebrow">POS-only internal report · not an official return</p>
            <h2>VAT Report</h2>
            <p><strong>Fabellion Construction and Development Corp.</strong></p>
            <p>Reporting period: <strong>{{ $periodLabel }}</strong></p>
            <p>Reporting timezone: <strong>Asia/Manila</strong></p>
            <p>Generated: <time datetime="{{ $generatedAt->toIso8601String() }}">{{ $generatedAt->format('F j, Y g:i:s A P') }}</time></p>
            <p>Coverage: recorded POS VAT activity only</p>
        </header>

        <p class="tax-report-disclaimer">This report includes recorded POS sales only. It is not a complete official tax return and is not a submitted filing.</p>

        <section class="tax-report-summary" aria-label="Selected period totals">
            <div><span>Taxable sales</span><strong>{{ $money($summary['taxable_sales']) }}</strong></div>
            <div><span>Output VAT</span><strong>{{ $money($summary['output_vat']) }}</strong></div>
            <div><span>VAT-inclusive total sales</span><strong>{{ $money($summary['total_sales']) }}</strong></div>
        </section>

        <section class="tax-report-transactions" aria-labelledby="tax-report-transactions-heading">
            <header>
                <div>
                    <h3 id="tax-report-transactions-heading">Matching recorded POS transactions</h3>
                    <p>{{ $summary['record_count'] }} recorded POS sales</p>
                </div>
            </header>
            <div class="vat-table-wrap tax-report-table-wrap tax-report-screen-only">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction no.</th>
                            <th>Completed (Asia/Manila)</th>
                            <th>Customer</th>
                            <th class="numeric">Taxable sales</th>
                            <th class="numeric">Output VAT</th>
                            <th class="numeric">VAT-inclusive total</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr wire:key="tax-report-{{ $record->id }}">
                                <td><strong>{{ $record->posTransaction->transaction_number }}</strong></td>
                                <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $record->posTransaction->customer_name ?: 'Walk-in customer' }}</td>
                                <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                                <td class="numeric">{{ $money($record->output_vat) }}</td>
                                <td class="numeric"><strong>{{ $money($record->total) }}</strong></td>
                                <td><button class="vat-detail-button" type="button" wire:click="viewRecord({{ $record->id }})">View details</button></td>
                            </tr>
                        @empty
                            <tr><td class="vat-empty" colspan="7">No recorded POS sales for this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Selected period totals</th>
                            <th class="numeric">{{ $money($summary['taxable_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['output_vat']) }}</th>
                            <th class="numeric">{{ $money($summary['total_sales']) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="tax-report-pagination tax-report-screen-only">{{ $records->links() }}</div>
        </section>
        <div class="tax-report-print-only">
            <h3>All matching recorded POS transactions</h3>
            <table>
                <thead>
                    <tr><th>Transaction no.</th><th>Completed (Asia/Manila)</th><th>Customer</th><th>Saved item lines</th><th class="numeric">Taxable sales</th><th class="numeric">Output VAT</th><th class="numeric">VAT-inclusive total</th></tr>
                </thead>
                <tbody>
                    @foreach ($printRecords as $record)
                        <tr>
                            <td>{{ $record->posTransaction->transaction_number }}</td>
                            <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $record->posTransaction->customer_name ?: 'Walk-in customer' }}</td>
                            <td>
                                @foreach ($record->posTransaction->lines as $line)
                                    <div>{{ $line->item_name }} — {{ number_format((float) $line->quantity, 2) }} {{ $line->unit }}; unit {{ $money($line->selling_price) }}; taxable {{ $money($line->line_subtotal) }}; VAT {{ $money($line->vat_amount) }}; total {{ $money($line->line_total) }}</div>
                                @endforeach
                            </td>
                            <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                            <td class="numeric">{{ $money($record->output_vat) }}</td>
                            <td class="numeric">{{ $money($record->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="4">Selected period totals</th><th class="numeric">{{ $money($summary['taxable_sales']) }}</th><th class="numeric">{{ $money($summary['output_vat']) }}</th><th class="numeric">{{ $money($summary['total_sales']) }}</th></tr>
                </tfoot>
            </table>
        </div>
    </article>

    @if ($selectedRecord)
        @php($sale = $selectedRecord->posTransaction)
        <div class="vat-modal-backdrop" role="presentation" wire:click.self="closeDetails">
            <section class="vat-detail-modal" role="dialog" aria-modal="true" aria-labelledby="tax-report-detail-heading">
                <header>
                    <h2 id="tax-report-detail-heading">Recorded POS transaction details</h2>
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
                        <thead><tr><th>Item</th><th>Quantity</th><th class="numeric">Selling price</th><th class="numeric">Taxable amount</th><th class="numeric">Saved VAT</th><th class="numeric">Saved total</th></tr></thead>
                        <tbody>
                            @foreach ($sale->lines as $line)
                                <tr wire:key="tax-report-line-{{ $line->id }}">
                                    <td>{{ $line->item_name }}</td>
                                    <td>{{ number_format((float) $line->quantity, 2) }} {{ $line->unit }}</td>
                                    <td class="numeric">{{ $money($line->selling_price) }}</td>
                                    <td class="numeric">{{ $money($line->line_subtotal) }}</td>
                                    <td class="numeric">{{ $money($line->vat_amount) }}</td>
                                    <td class="numeric">{{ $money($line->line_total) }}</td>
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
