@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
@php($manilaDate = fn ($date) => $date->copy()->timezone('Asia/Manila'))
<section class="vat-page vat-return-page" aria-labelledby="vat-return-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-return-heading">VAT Return Preparation</h1>
            <p class="page-subtitle">Live, read-only POS VAT preparation worksheet for review. Not a complete official return.</p>
        </div>
        <span class="vat-rate-badge">POS-only preparation</span>
    </header>

    <p class="temporary-notice" role="note">Preparation aid only: this is not an official Form 2550Q, not a complete official return and not a submitted filing. It does not map official form fields or submit anything to BIR. Input VAT is not captured here; that does not mean the company has no actual input VAT. Additional completed POS sales can change these live figures.</p>

    <div class="vat-return-controls vat-return-screen-only">
        <div class="vat-return-period-controls">
            <label>
                <span>Year</span>
                <select wire:model.live="selectedYear">
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Calendar quarter</span>
                <select wire:model.live="selectedQuarter">
                    @foreach ($quarters as $number => $label)
                        <option value="{{ $number }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="vat-return-actions">
            <button class="vat-detail-button" type="button" wire:click="exportCsv">Download summary CSV</button>
            <button class="vat-detail-button" type="button" onclick="document.body.classList.add('printing-vat-return'); window.print(); setTimeout(() => document.body.classList.remove('printing-vat-return'), 100)">Print / Save as PDF</button>
        </div>
    </div>

    <article id="vat-return-print" class="vat-return-document" aria-label="POS VAT preparation worksheet">
        <header class="vat-return-document-heading">
            <h2>Fabellion Construction and Development Corp.</h2>
            <p>Quarterly VAT preparation worksheet · {{ $periodLabel }}</p>
            <p>Generated {{ $generatedAt->format('Y-m-d H:i:s P') }} · Reporting timezone: Asia/Manila</p>
        </header>

        <p class="vat-return-disclaimer">POS-only preparation aid. This is not an official Form 2550Q, not a complete official return and not a submitted filing. It is not the company’s final VAT liability.</p>

        <section aria-labelledby="vat-return-totals-heading">
            <h3 id="vat-return-totals-heading">Recorded POS VAT totals</h3>
            <div class="vat-table-wrap">
                <table>
                    <thead><tr><th>Worksheet item</th><th class="numeric">Amount / status</th></tr></thead>
                    <tbody>
                        <tr><td>Taxable sales</td><td class="numeric">{{ $money($summary['taxable_sales']) }}</td></tr>
                        <tr><td>Recorded output VAT</td><td class="numeric">{{ $money($summary['output_vat']) }}</td></tr>
                        <tr><td>VAT-inclusive total</td><td class="numeric">{{ $money($summary['total_sales']) }}</td></tr>
                        <tr><td>Input VAT not captured</td><td class="numeric">Not captured</td></tr>
                        <tr><td>Deductions applied</td><td class="numeric">{{ $money($summary['deductions_applied']) }}</td></tr>
                        <tr><td><strong>POS-only VAT payable estimate</strong></td><td class="numeric"><strong>{{ $money($summary['vat_payable_estimate']) }}</strong></td></tr>
                    </tbody>
                </table>
            </div>
            <p class="vat-return-note">Output VAT sums saved transaction amounts, preserving checkout-level rounding. Input VAT is unavailable in this worksheet; deductions are shown as PHP 0.00 and do not establish that the company incurred no input VAT. The estimate covers recorded POS sales only and is not a determination of final VAT liability.</p>
            @if ($summary['record_count'] === 0)
                <p class="temporary-notice" role="status">No recorded POS sales for {{ $periodLabel }}. All totals are PHP 0.00.</p>
            @endif
        </section>
    </article>

    <section class="vat-record-card vat-return-records vat-return-screen-only" aria-labelledby="vat-return-records-heading">
        <header>
            <div>
                <h2 id="vat-return-records-heading">{{ $periodLabel }} supporting transactions</h2>
                <p>Saved completed POS sales for this calendar quarter. Review is read-only.</p>
            </div>
            <span class="vat-record-count">{{ $records->count() }} recorded sales</span>
        </header>
        <div class="vat-table-wrap">
            <table>
                <thead><tr><th>Transaction no.</th><th>Manila completion</th><th class="numeric">Taxable sales</th><th class="numeric">Output VAT</th><th class="numeric">VAT-inclusive total</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr wire:key="vat-preparation-record-{{ $record->id }}">
                            <td><strong>{{ $record->posTransaction->transaction_number }}</strong></td>
                            <td>{{ $manilaDate($record->completed_at)->format('Y-m-d H:i') }}</td>
                            <td class="numeric">{{ $money($record->taxable_sales) }}</td>
                            <td class="numeric">{{ $money($record->output_vat) }}</td>
                            <td class="numeric">{{ $money($record->total) }}</td>
                            <td><button class="vat-detail-button" type="button" wire:click="viewRecord({{ $record->id }})">View details</button></td>
                        </tr>
                    @empty
                        <tr><td class="vat-empty" colspan="6">No recorded POS sales.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedRecord)
        @php($sale = $selectedRecord->posTransaction)
        <div class="vat-modal-backdrop vat-return-screen-only" role="presentation" wire:click.self="closeDetails">
            <section class="vat-detail-modal" role="dialog" aria-modal="true" aria-labelledby="vat-preparation-detail-heading">
                <header>
                    <h2 id="vat-preparation-detail-heading">Saved sale details · {{ $sale->transaction_number }}</h2>
                    <button class="vat-modal-close" type="button" wire:click="closeDetails" aria-label="Close details">&times;</button>
                </header>
                <p class="vat-modal-note">{{ $sale->customer_name ?: 'Walk-in customer' }} · {{ $manilaDate($selectedRecord->completed_at)->format('F j, Y g:i A') }} (Asia/Manila)</p>
                <dl>
                    <div><dt>Taxable sales</dt><dd>{{ $money($selectedRecord->taxable_sales) }}</dd></div>
                    <div><dt>Recorded output VAT</dt><dd>{{ $money($selectedRecord->output_vat) }}</dd></div>
                    <div><dt>VAT-inclusive total</dt><dd>{{ $money($selectedRecord->total) }}</dd></div>
                </dl>
                <h3>Saved sale items</h3>
                <div class="vat-table-wrap">
                    <table>
                        <thead><tr><th>Item</th><th>Quantity</th><th class="numeric">Selling price</th><th class="numeric">Line taxable amount</th></tr></thead>
                        <tbody>
                            @foreach ($sale->lines as $line)
                                <tr wire:key="vat-preparation-line-{{ $line->id }}">
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
