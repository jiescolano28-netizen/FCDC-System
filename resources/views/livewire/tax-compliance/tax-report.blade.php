@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
@php($manilaDate = fn ($date) => $date->copy()->timezone('Asia/Manila'))
<section class="tax-report-page" aria-labelledby="tax-report-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="tax-report-heading">VAT Report</h1>
            <p class="page-subtitle">Recorded POS sales summaries with monthly and quarterly breakdowns · completion dates in Asia/Manila.</p>
        </div>
        <span class="vat-rate-badge">Internal reporting aid</span>
    </header>

    <section class="vat-filter-panel" aria-label="Select VAT report period">
        <div class="vat-filter-grid vat-report-filter-grid">
            <label for="tax-report-type">
                <span>Summary type</span>
                <select id="tax-report-type" wire:model.live="periodType">
                    <option value="monthly">Monthly report</option>
                    <option value="quarterly">Quarterly report</option>
                    <option value="custom">Custom date range</option>
                </select>
            </label>
            @if ($periodType !== 'custom')
                <label for="tax-report-year">
                    <span>Year</span>
                    <select id="tax-report-year" wire:model.live="selectedYear">
                        @foreach ($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="tax-report-period">
                    <span>{{ $periodType === 'quarterly' ? 'Quarter' : 'Month' }}</span>
                    <select id="tax-report-period" wire:model.live="selectedPeriod">
                        @foreach ($periods as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <label for="tax-report-start">
                    <span>From (Manila date)</span>
                    <input id="tax-report-start" type="date" wire:model.live="startDate">
                </label>
                <label for="tax-report-end">
                    <span>Through (Manila date)</span>
                    <input id="tax-report-end" type="date" wire:model.live="endDate">
                </label>
            @endif
        </div>
        <div class="vat-filter-footer">
            <p class="vat-filter-count">{{ $periodLabel }} · {{ $startDate }} through {{ $endDate }} · {{ $summary['record_count'] }} recorded sale(s)</p>
            <div class="vat-filter-actions">
                <a class="vat-detail-button tax-report-export" href="#" wire:click.prevent="exportCsv">Export CSV</a>
                <button class="vat-detail-button tax-report-print" type="button" onclick="window.print()" title="Open the print dialog and choose Save as PDF">Export PDF</button>
            </div>
        </div>
    </section>

    <article id="tax-report-document" class="tax-report-document" aria-label="Selected POS-sourced VAT report">
        <header class="tax-report-document-header">
            <p class="tax-report-eyebrow">POS-only internal report · not an official return</p>
            <h2>VAT Report</h2>
            <p><strong>Fabellion Construction and Development Corp.</strong></p>
            <p>Reporting period: <strong>{{ $periodLabel }}</strong> · {{ $startDate }} through {{ $endDate }}</p>
            <p>Reporting timezone: <strong>Asia/Manila</strong></p>
            <p>Generated: <time datetime="{{ $generatedAt->toIso8601String() }}">{{ $generatedAt->format('F j, Y g:i:s A P') }}</time></p>
            <p>Coverage: recorded POS VAT activity only</p>
        </header>

        <p class="tax-report-disclaimer">This report includes recorded POS sales only. It is not a complete official tax return and is not a submitted filing.</p>

        @include('livewire.tax-compliance.partials.summary-cards', [
            'summary' => $summary,
            'ariaLabel' => $periodLabel.' VAT report totals',
        ])

        <section class="tax-report-breakdown" aria-labelledby="tax-report-monthly-heading">
            <header>
                <div>
                    <h3 id="tax-report-monthly-heading">Monthly sales summary</h3>
                    <p>Every month in the selected report range · recorded POS sales and recorded input VAT.</p>
                </div>
            </header>
            <div class="vat-table-wrap tax-report-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Date range</th>
                            <th class="numeric">Sales</th>
                            <th class="numeric">VATable</th>
                            <th class="numeric">Zero-rated</th>
                            <th class="numeric">VAT-exempt</th>
                            <th class="numeric">Non-VAT / outside scope</th>
                            <th class="numeric">Output VAT</th>
                            <th class="numeric">Input VAT</th>
                            <th class="numeric">Estimated payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyBreakdown as $row)
                            @php($monthSummary = $row['summary'])
                            <tr wire:key="tax-report-month-{{ $row['key'] }}">
                                <td><strong>{{ $row['label'] }}</strong></td>
                                <td>{{ $row['start_date'] }} through {{ $row['end_date'] }}</td>
                                <td class="numeric">{{ $monthSummary['record_count'] }}</td>
                                <td class="numeric">{{ $money($monthSummary['taxable_sales']) }}</td>
                                <td class="numeric">{{ $money($monthSummary['zero_rated_sales']) }}</td>
                                <td class="numeric">{{ $money($monthSummary['exempt_sales']) }}</td>
                                <td class="numeric">{{ $money($monthSummary['non_vat_sales']) }}</td>
                                <td class="numeric">{{ $money($monthSummary['output_vat']) }}</td>
                                <td class="numeric">{{ $money($monthSummary['input_vat']) }}</td>
                                <td class="numeric"><strong>{{ $money($monthSummary['vat_payable_estimate']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">{{ $periodLabel }} totals</th>
                            <th class="numeric">{{ $summary['record_count'] }}</th>
                            <th class="numeric">{{ $money($summary['taxable_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['zero_rated_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['exempt_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['non_vat_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['output_vat']) }}</th>
                            <th class="numeric">{{ $money($summary['input_vat']) }}</th>
                            <th class="numeric">{{ $money($summary['vat_payable_estimate']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="tax-report-breakdown" aria-labelledby="tax-report-quarterly-heading">
            <header>
                <div>
                    <h3 id="tax-report-quarterly-heading">Quarterly sales summary</h3>
                    <p>Calendar quarters in the selected report range · recorded POS sales and recorded input VAT.</p>
                </div>
            </header>
            <div class="vat-table-wrap tax-report-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Quarter</th>
                            <th>Date range</th>
                            <th class="numeric">Sales</th>
                            <th class="numeric">VATable</th>
                            <th class="numeric">Zero-rated</th>
                            <th class="numeric">VAT-exempt</th>
                            <th class="numeric">Non-VAT / outside scope</th>
                            <th class="numeric">Output VAT</th>
                            <th class="numeric">Input VAT</th>
                            <th class="numeric">Estimated payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quarterlyBreakdown as $row)
                            @php($quarterSummary = $row['summary'])
                            <tr wire:key="tax-report-quarter-{{ $row['key'] }}">
                                <td><strong>{{ $row['label'] }}</strong></td>
                                <td>{{ $row['start_date'] }} through {{ $row['end_date'] }}</td>
                                <td class="numeric">{{ $quarterSummary['record_count'] }}</td>
                                <td class="numeric">{{ $money($quarterSummary['taxable_sales']) }}</td>
                                <td class="numeric">{{ $money($quarterSummary['zero_rated_sales']) }}</td>
                                <td class="numeric">{{ $money($quarterSummary['exempt_sales']) }}</td>
                                <td class="numeric">{{ $money($quarterSummary['non_vat_sales']) }}</td>
                                <td class="numeric">{{ $money($quarterSummary['output_vat']) }}</td>
                                <td class="numeric">{{ $money($quarterSummary['input_vat']) }}</td>
                                <td class="numeric"><strong>{{ $money($quarterSummary['vat_payable_estimate']) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">{{ $periodLabel }} totals</th>
                            <th class="numeric">{{ $summary['record_count'] }}</th>
                            <th class="numeric">{{ $money($summary['taxable_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['zero_rated_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['exempt_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['non_vat_sales']) }}</th>
                            <th class="numeric">{{ $money($summary['output_vat']) }}</th>
                            <th class="numeric">{{ $money($summary['input_vat']) }}</th>
                            <th class="numeric">{{ $money($summary['vat_payable_estimate']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        @if ($summary['input_vat_record_count'] > 0)
            <p class="tax-report-breakdown-note">Input VAT of {{ $money($summary['input_vat']) }} comes from {{ $summary['input_vat_record_count'] }} saved Reworking Cost Record(s). The VAT payable estimate is recorded output VAT less recorded input VAT; it is not a determination of final VAT liability.</p>
        @else
            <p class="tax-report-breakdown-note">Input VAT not captured. The VAT payable estimate is recorded output VAT only and does not establish that the company incurred no input VAT.</p>
        @endif

        <section class="tax-report-transactions" aria-labelledby="tax-report-transactions-heading">
            <header>
                <div>
                    <h3 id="tax-report-transactions-heading">Supporting recorded POS transactions</h3>
                    <p>{{ $summary['record_count'] }} recorded POS sales · showing the latest 20 on screen; the printed report and CSV include every matching transaction.</p>
                </div>
            </header>
            <div class="vat-table-wrap tax-report-table-wrap tax-report-screen-only">
                <table>
                    <thead>
                        <tr>
                            <th>Transaction no.</th>
                            <th>Completed (Asia/Manila)</th>
                            <th>Customer</th>
                            <th>Tax classification</th>
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
                                <td>{{ \App\Support\TaxClassification::label($record->tax_classification) }}</td>
                                <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                                <td class="numeric">{{ $money($record->output_vat) }}</td>
                                <td class="numeric"><strong>{{ $money($record->total) }}</strong></td>
                                <td><button class="vat-detail-button" type="button" wire:click="viewRecord({{ $record->id }})">View details</button></td>
                            </tr>
                        @empty
                            <tr><td class="vat-empty" colspan="8">No recorded POS sales for this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Selected period totals</th>
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

        <section class="tax-report-transactions" aria-labelledby="tax-report-input-vat-heading">
            <header>
                <div>
                    <h3 id="tax-report-input-vat-heading">Supporting reworking-cost input VAT records</h3>
                    <p>{{ $summary['input_vat_record_count'] }} saved batch record(s) · input VAT {{ $money($summary['input_vat']) }}</p>
                </div>
            </header>
            <div class="vat-table-wrap tax-report-table-wrap tax-report-screen-only">
                <table>
                    <thead>
                        <tr>
                            <th>Batch number</th>
                            <th>Project</th>
                            <th>Record date (Asia/Manila)</th>
                            <th>Description</th>
                            <th class="numeric">Reworking cost</th>
                            <th class="numeric">Input VAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inputVatRecords as $record)
                            <tr wire:key="tax-report-input-{{ $record['source_key'] }}">
                                <td><strong>{{ $record['batch_number'] }}</strong></td>
                                <td>{{ $record['project'] }}</td>
                                <td>{{ $record['date'] }}</td>
                                <td>{{ $record['description'] }}</td>
                                <td class="numeric">{{ $money($record['total_reworking_cost']) }}</td>
                                <td class="numeric"><strong>{{ $money($record['input_vat']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td class="vat-empty" colspan="6">No recorded input VAT from reworking cost records for this period.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">Input VAT total</th>
                            <th class="numeric">{{ $money($summary['input_vat_reworking_cost']) }}</th>
                            <th class="numeric">{{ $money($summary['input_vat']) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="tax-report-print-only">
            <h3>All matching recorded POS transactions</h3>
            <table>
                <thead>
                    <tr><th>Transaction no.</th><th>Completed (Asia/Manila)</th><th>Customer</th><th>Tax classification</th><th>Saved item lines</th><th class="numeric">Taxable sales</th><th class="numeric">Output VAT</th><th class="numeric">VAT-inclusive total</th></tr>
                </thead>
                <tbody>
                    @foreach ($printRecords as $record)
                        <tr>
                            <td>{{ $record->posTransaction->transaction_number }}</td>
                            <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $record->posTransaction->customer_name ?: 'Walk-in customer' }}</td>
                            <td>{{ \App\Support\TaxClassification::label($record->tax_classification) }}</td>
                            <td>
                                @foreach ($record->posTransaction->lines as $line)
                                    <div>{{ $line->item_name }} ({{ \App\Support\TaxClassification::label($line->tax_classification) }}) — {{ number_format((float) $line->quantity, 2) }} {{ $line->unit }}; unit {{ $money($line->selling_price) }}; taxable {{ $money($line->line_subtotal) }}; VAT {{ $money($line->vat_amount) }}; total {{ $money($line->line_total) }}</div>
                                @endforeach
                            </td>
                            <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                            <td class="numeric">{{ $money($record->output_vat) }}</td>
                            <td class="numeric">{{ $money($record->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="5">Selected period totals</th><th class="numeric">{{ $money($summary['taxable_sales']) }}</th><th class="numeric">{{ $money($summary['output_vat']) }}</th><th class="numeric">{{ $money($summary['total_sales']) }}</th></tr>
                </tfoot>
            </table>

            <h3>Matching reworking-cost input VAT records</h3>
            <table>
                <thead>
                    <tr><th>Batch number</th><th>Project</th><th>Record date (Asia/Manila)</th><th>Description</th><th class="numeric">Reworking cost</th><th class="numeric">Input VAT</th></tr>
                </thead>
                <tbody>
                    @forelse ($inputVatRecords as $record)
                        <tr>
                            <td>{{ $record['batch_number'] }}</td>
                            <td>{{ $record['project'] }}</td>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['description'] }}</td>
                            <td class="numeric">{{ $money($record['total_reworking_cost']) }}</td>
                            <td class="numeric">{{ $money($record['input_vat']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No recorded input VAT from reworking cost records for this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><th colspan="4">Input VAT total</th><th class="numeric">{{ $money($summary['input_vat_reworking_cost']) }}</th><th class="numeric">{{ $money($summary['input_vat']) }}</th></tr>
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
                    <div><dt>Tax classification</dt><dd>{{ \App\Support\TaxClassification::label($selectedRecord->tax_classification) }}</dd></div>
                    <div><dt>VATable sales</dt><dd>{{ $money($selectedRecord->taxable_sales) }}</dd></div>
                    <div><dt>Zero-rated sales</dt><dd>{{ $money($selectedRecord->zero_rated_sales) }}</dd></div>
                    <div><dt>VAT-exempt sales</dt><dd>{{ $money($selectedRecord->exempt_sales) }}</dd></div>
                    <div><dt>Non-VAT / outside scope</dt><dd>{{ $money($selectedRecord->non_vat_sales) }}</dd></div>
                    <div><dt>VAT rate</dt><dd>{{ number_format((float) $selectedRecord->vat_rate * 100, 2) }}%</dd></div>
                    <div><dt>Output VAT</dt><dd>{{ $money($selectedRecord->output_vat) }}</dd></div>
                    <div><dt>VAT-inclusive total</dt><dd>{{ $money($selectedRecord->total) }}</dd></div>
                </dl>
                <h3>Saved sale items</h3>
                <div class="vat-table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Tax classification</th><th>Quantity</th><th class="numeric">Selling price</th><th class="numeric">Taxable amount</th><th class="numeric">Saved VAT</th><th class="numeric">Saved total</th></tr></thead>
                        <tbody>
                            @foreach ($sale->lines as $line)
                                <tr wire:key="tax-report-line-{{ $line->id }}">
                                    <td>{{ $line->item_name }}</td>
                                    <td>{{ \App\Support\TaxClassification::label($line->tax_classification) }}</td>
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
