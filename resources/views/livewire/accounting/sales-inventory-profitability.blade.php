@php
    $money = fn (int $cents): string => ($cents < 0 ? '−₱' : '₱').number_format(abs($cents) / 100, 2);
    $percent = fn (?float $value): string => $value === null ? '—' : number_format($value, 2).'%';
    $quantity = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
@endphp
<section class="profitability-page" aria-labelledby="profitability-heading">
    <header class="page-heading profitability-heading">
        <div>
            <h1 id="profitability-heading">Sales and Inventory Profitability Report</h1>
            <p class="page-subtitle">Actual completed POS sales, recorded inventory cost, and reworking costs for the selected Manila period.</p>
        </div>
        <button class="vat-detail-button" type="button" onclick="window.print()">Print report</button>
    </header>

    <div class="profitability-controls" role="group" aria-label="Profitability report period">
        <label>
            <span>From date</span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </div>

    <article id="profitability-report" class="profitability-report" aria-labelledby="profitability-report-heading">
        <header class="profitability-report-heading">
            <p class="profitability-eyebrow">FCDC accounting report · PHP</p>
            <h2 id="profitability-report-heading">Sales and Inventory Profitability Report</h2>
            <p>Inclusive Manila period: {{ $report['from'] !== '' ? $report['from'] : ($fromDate !== '' ? $fromDate : 'Not selected') }} through {{ $report['to'] !== '' ? $report['to'] : ($toDate !== '' ? $toDate : 'Not selected') }}</p>
            <p>Generated: {{ $generatedAt }}</p>
            <p>Scope: completed POS sales and persisted Reworking Cost Records. This report is not audited, filed, or legally certified.</p>
        </header>

        @if (! $report['available'])
            <div class="profitability-empty" role="alert">
                <p>Profitability report unavailable.</p>
                <span>{{ $report['error'] }}</span>
            </div>
        @else
            <div class="profitability-summary-grid" aria-label="Profitability summary">
                <div class="profitability-stat">
                    <span>Sales revenue</span>
                    <strong>{{ $money($report['sales_revenue_cents']) }}</strong>
                    <small>{{ $report['sale_count'] }} completed POS {{ $report['sale_count'] === 1 ? 'sale' : 'sales' }} · VAT-exclusive</small>
                </div>
                <div class="profitability-stat">
                    <span>Cost of goods sold</span>
                    <strong>{{ $money($report['cogs_cents']) }}</strong>
                    <small>Recorded inventory cost of items sold</small>
                </div>
                <div class="profitability-stat">
                    <span>Gross profit</span>
                    <strong>{{ $money($report['gross_profit_cents']) }}</strong>
                    <small>Margin {{ $percent($report['gross_margin_percent']) }}</small>
                </div>
                <div class="profitability-stat">
                    <span>Reworking costs</span>
                    <strong>{{ $money($report['reworking_cost_cents']) }}</strong>
                    <small>{{ $report['reworking_record_count'] }} {{ $report['reworking_record_count'] === 1 ? 'record' : 'records' }} · VAT-exclusive</small>
                </div>
                <div class="profitability-stat">
                    <span>Profit after reworking</span>
                    <strong class="{{ $report['profit_after_reworking_cents'] < 0 ? 'profitability-negative' : '' }}">{{ $money($report['profit_after_reworking_cents']) }}</strong>
                    <small>Margin {{ $percent($report['profit_margin_percent']) }}</small>
                </div>
                <div class="profitability-stat">
                    <span>Inventory cost on hand</span>
                    <strong>{{ $money($report['current_inventory_cost_cents']) }}</strong>
                    <small>Current recorded carrying value</small>
                </div>
            </div>

            @if ($report['sale_count'] === 0 && $report['reworking_record_count'] === 0)
                <p class="profitability-no-activity" role="status">No completed POS sales or reworking cost records were found for this period.</p>
            @endif

            <table class="profitability-table profitability-statement" aria-label="Sales and inventory profitability statement">
                <tbody>
                    <tr>
                        <th scope="row">Sales revenue (VAT-exclusive)</th>
                        <td>{{ $money($report['sales_revenue_cents']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Output VAT (memo, not revenue)</th>
                        <td>{{ $money($report['output_vat_cents']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Total collected</th>
                        <td>{{ $money($report['total_collected_cents']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Cost of goods sold (recorded inventory cost)</th>
                        <td>({{ $money($report['cogs_cents']) }})</td>
                    </tr>
                    <tr class="profitability-total">
                        <th scope="row">Gross profit</th>
                        <td>{{ $money($report['gross_profit_cents']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Gross margin</th>
                        <td>{{ $percent($report['gross_margin_percent']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Reworking costs (VAT-exclusive)</th>
                        <td>({{ $money($report['reworking_cost_cents']) }})</td>
                    </tr>
                    <tr class="profitability-total profitability-final">
                        <th scope="row">Profit from recovered materials sales</th>
                        <td>{{ $money($report['profit_after_reworking_cents']) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Profit margin</th>
                        <td>{{ $percent($report['profit_margin_percent']) }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="profitability-section" aria-labelledby="inventory-cost-heading">
                <h3 id="inventory-cost-heading">Inventory cost summary</h3>
                <table class="profitability-table" aria-label="Inventory cost summary">
                    <tbody>
                        <tr>
                            <th scope="row">Current recorded inventory cost on hand</th>
                            <td>{{ $money($report['current_inventory_cost_cents']) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Inventory cost released to POS sales (COGS)</th>
                            <td>{{ $money($report['cogs_cents']) }}</td>
                        </tr>
                        <tr>
                            <th scope="row">Reworking costs recorded in period (VAT-exclusive)</th>
                            <td>{{ $money($report['reworking_cost_cents']) }}</td>
                        </tr>
                        <tr class="profitability-total">
                            <th scope="row">Total recorded cost used in profitability</th>
                            <td>{{ $money($report['total_period_cost_cents']) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="profitability-note">Inventory on hand is an asset and is not deducted from sales revenue; only the recorded inventory cost of items sold (COGS) is deducted. Reworking costs are stored separately and are not included in the inventory carrying value or COGS in the current system, so they are deducted once. Input VAT is excluded from reworking cost.</p>
            </section>

            <section class="profitability-section" aria-labelledby="calculation-basis-heading">
                <h3 id="calculation-basis-heading">Calculation basis</h3>
                <ul class="profitability-list">
                    <li>Sales revenue uses the saved VAT-exclusive subtotal of completed POS transactions dated in the selected Manila period.</li>
                    <li>COGS uses each sold line's frozen recorded inventory cost. @if ($report['fallback_cost_line_count'] > 0) {{ $report['fallback_cost_line_count'] }} line(s) predate the frozen-cost field and use the saved unit cost × quantity. @endif</li>
                    <li>Reworking costs use each persisted Reworking Cost Record once for the selected period. Input VAT is excluded, and those records are not added again to COGS or inventory cost.</li>
                    <li>No hard-coded sample amounts or accounting cutover balances are used.</li>
                </ul>
                @if ($report['line_revenue_difference_cents'] !== 0)
                    <p class="profitability-warning" role="status">Saved transaction subtotals differ from the sum of saved line subtotals by {{ $money($report['line_revenue_difference_cents']) }}. Revenue uses the saved transaction subtotal.</p>
                @endif
            </section>

            <section class="profitability-section" aria-labelledby="sales-detail-heading">
                <h3 id="sales-detail-heading">Completed POS sales</h3>
                @if ($report['sale_rows'] === [])
                    <p class="profitability-no-activity" role="status">No completed POS sales in this period.</p>
                @else
                    <div class="profitability-table-wrap">
                        <table class="profitability-table profitability-detail-table" aria-label="Completed POS sales detail">
                            <thead>
                                <tr>
                                    <th scope="col">Date (Manila)</th>
                                    <th scope="col">Transaction</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col" class="numeric">Revenue</th>
                                    <th scope="col" class="numeric">VAT</th>
                                    <th scope="col" class="numeric">Total</th>
                                    <th scope="col" class="numeric">COGS</th>
                                    <th scope="col" class="numeric">Gross profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['sale_rows'] as $sale)
                                    <tr>
                                        <td>{{ $sale['completed_at'] }}</td>
                                        <td>{{ $sale['transaction_number'] }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $sale['payment_method'])) }}</td>
                                        <td class="numeric">{{ $money($sale['revenue_cents']) }}</td>
                                        <td class="numeric">{{ $money($sale['vat_cents']) }}</td>
                                        <td class="numeric">{{ $money($sale['total_cents']) }}</td>
                                        <td class="numeric">{{ $money($sale['cogs_cents']) }}</td>
                                        <td class="numeric">{{ $money($sale['gross_profit_cents']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="profitability-section" aria-labelledby="item-profitability-heading">
                <h3 id="item-profitability-heading">Profitability by sold item</h3>
                @if ($report['item_rows']->isEmpty())
                    <p class="profitability-no-activity" role="status">No sold-item detail in this period.</p>
                @else
                    <div class="profitability-table-wrap">
                        <table class="profitability-table profitability-detail-table" aria-label="Profitability by sold item">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="numeric">Quantity</th>
                                    <th scope="col" class="numeric">Revenue</th>
                                    <th scope="col" class="numeric">COGS</th>
                                    <th scope="col" class="numeric">Gross profit</th>
                                    <th scope="col" class="numeric">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['item_rows'] as $item)
                                    <tr>
                                        <td>{{ $item['item_name'] }} <small>{{ $item['inventory_code'] }}</small></td>
                                        <td>{{ $item['category'] }}</td>
                                        <td class="numeric">{{ $quantity($item['quantity']) }}</td>
                                        <td class="numeric">{{ $money($item['revenue_cents']) }}</td>
                                        <td class="numeric">{{ $money($item['cogs_cents']) }}</td>
                                        <td class="numeric">{{ $money($item['gross_profit_cents']) }}</td>
                                        <td class="numeric">{{ $percent($item['gross_margin_percent']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="profitability-section" aria-labelledby="reworking-cost-heading">
                <h3 id="reworking-cost-heading">Reworking cost records</h3>
                @if ($report['reworking_records']->isEmpty())
                    <p class="profitability-no-activity" role="status">No reworking cost records in this period.</p>
                @else
                    <div class="profitability-table-wrap">
                        <table class="profitability-table profitability-detail-table" aria-label="Reworking cost records">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Project</th>
                                    <th scope="col">Batch</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="numeric">Reworking cost</th>
                                    <th scope="col" class="numeric">Input VAT (memo)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['reworking_records'] as $record)
                                    <tr>
                                        <td>{{ $record['date'] }}</td>
                                        <td>{{ $record['project'] }}</td>
                                        <td>{{ $record['batch_number'] }}</td>
                                        <td>{{ $record['description'] }}</td>
                                        <td class="numeric">{{ $money($record['cost_cents']) }}</td>
                                        <td class="numeric">{{ $money($record['input_vat_cents']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="profitability-total">
                                    <th scope="row" colspan="4">Total reworking cost</th>
                                    <td class="numeric">{{ $money($report['reworking_cost_cents']) }}</td>
                                    <td class="numeric">{{ $money($report['reworking_input_vat_cents']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <p class="profitability-generated">Generated {{ $generatedAt }} · Amounts in Philippine pesos (PHP).</p>
        @endif
    </article>
</section>
