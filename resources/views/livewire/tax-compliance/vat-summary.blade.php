@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
<section class="vat-page" aria-labelledby="vat-summary-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-summary-heading">VAT Summary</h1>
            <p class="page-subtitle">Recorded VAT totals for completed POS sales and recorded reworking-cost input VAT · Asia/Manila.</p>
        </div>
        <span class="vat-rate-badge">POS-only · recorded input VAT</span>
    </header>

    <p class="temporary-notice" role="note">Figures reconcile to saved VAT Records and use each source sale’s completed date in Asia/Manila. This summary covers recorded POS sales only; it is not the company’s final VAT liability or a filed return.</p>

    <section class="vat-filter-panel" aria-label="Select VAT summary period">
        <div class="vat-filter-grid">
            <label>
                <span>Summary period</span>
                <select wire:model.live="periodType">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </label>
            <label>
                <span>Year</span>
                <select wire:model.live="selectedYear">
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            @if ($periodType !== 'yearly')
                <label>
                    <span>{{ $periodType === 'quarterly' ? 'Quarter' : 'Month' }}</span>
                    <select wire:model.live="selectedPeriod">
                        @foreach ($periods as $number => $label)
                            <option value="{{ $number }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>
        <div class="vat-filter-footer">
            <p class="vat-filter-count">{{ $periodLabel }} · {{ $summary['record_count'] }} recorded POS sale(s) · {{ $summary['input_vat_record_count'] }} input VAT record(s)</p>
            <a class="vat-secondary-button" href="{{ route('tax.vat-records') }}">View VAT Records</a>
        </div>
    </section>

    @include('livewire.tax-compliance.partials.summary-cards', [
        'summary' => $summary,
        'ariaLabel' => $periodLabel.' recorded VAT totals',
    ])

    <section class="dashboard-card vat-summary-context" aria-labelledby="vat-summary-context-heading">
        <div class="dashboard-card-heading">
            <h2 id="vat-summary-context-heading">{{ $periodLabel }} coverage and reconciliation</h2>
            <span class="vat-record-count">Total sales including VAT: {{ $money($summary['total_sales']) }}</span>
        </div>
        @if ($summary['input_vat_record_count'] > 0)
            <p class="dashboard-chart-note">Input VAT of {{ $money($summary['input_vat']) }} comes from {{ $summary['input_vat_record_count'] }} saved Reworking Cost Record(s) for {{ $summary['input_vat_batch_count'] }} batch(es). Deductions applied: {{ $money($summary['deductions_applied']) }}. This is a POS-plus-reworking preparation estimate, not a final company VAT liability.</p>
            <p class="dashboard-chart-note">Reworking cost total: {{ $money($summary['input_vat_reworking_cost']) }} · Input VAT status: Captured from saved Reworking Cost Records.</p>
        @else
            <p class="dashboard-chart-note">Input VAT not captured. Deductions applied: {{ $money($summary['deductions_applied']) }}. This does not establish that the company incurred no input VAT.</p>
            <p class="dashboard-chart-note">Reworking cost total: {{ $money($summary['input_vat_reworking_cost']) }} · Input VAT status: Not captured.</p>
        @endif
        <p class="dashboard-chart-note">Output VAT is the sum of saved record amounts. Checkout-level rounding can differ from applying 12% once to the aggregate taxable sales.</p>
        @if ($summary['record_count'] === 0)
            <p class="temporary-notice" role="status">No recorded POS sales for {{ $periodLabel }}.</p>
        @endif
    </section>
</section>
