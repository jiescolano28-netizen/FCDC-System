@php($money = fn ($amount) => '₱'.number_format((float) $amount, 2))
<section class="vat-page" aria-labelledby="vat-summary-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-summary-heading">VAT Summary</h1>
            <p class="page-subtitle">Recorded VAT summary for POS sales only. This is not the company’s final VAT liability or a filed return.</p>
        </div>
        <span class="vat-rate-badge">POS-only coverage</span>
    </header>

    <p class="temporary-notice" role="note">Figures reconcile to saved VAT Records and use each source sale’s completed date in Asia/Manila. This summary does not include non-POS sales or purchase-based input VAT.</p>

    <div class="vat-summary-controls">
        <label>
            <span>Reporting period</span>
            <select wire:model.live="periodType">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
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
        <label>
            <span>{{ $periodType === 'quarterly' ? 'Quarter' : 'Month' }}</span>
            <select wire:model.live="selectedPeriod">
                @foreach ($periods as $number => $label)
                    <option value="{{ $number }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <section class="dashboard-stats reports-stats vat-summary-stats" aria-label="{{ $periodLabel }} POS-only VAT totals">
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Taxable sales</p>
            <p class="dashboard-stat-value">{{ $money($summary['taxable_sales']) }}</p>
        </article>
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Output VAT</p>
            <p class="dashboard-stat-value">{{ $money($summary['output_vat']) }}</p>
        </article>
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Total sales including VAT</p>
            <p class="dashboard-stat-value">{{ $money($summary['total_sales']) }}</p>
        </article>
        <article class="dashboard-card">
            <p class="dashboard-stat-label">POS-only VAT payable estimate</p>
            <p class="dashboard-stat-value">{{ $money($summary['vat_payable_estimate']) }}</p>
        </article>
    </section>

    <section class="dashboard-card vat-summary-records">
        <div class="dashboard-card-heading">
            <h2>{{ $periodLabel }} recorded VAT totals</h2>
            <a class="vat-detail-button" href="{{ route('tax.vat-records') }}">View VAT Records</a>
        </div>
        <p class="dashboard-chart-note">Input VAT not captured. Deductions applied: {{ $money($summary['deductions_applied']) }}. This does not establish that the company incurred no input VAT.</p>
        <p class="dashboard-chart-note">Output VAT is the sum of saved record amounts. Checkout-level rounding can differ from applying 12% once to the aggregate taxable sales.</p>
        @if ($summary['record_count'] === 0)
            <p class="temporary-notice" role="status">No recorded POS sales for {{ $periodLabel }}.</p>
        @endif
        <dl class="vat-summary-totals">
            <div><dt>Selected period</dt><dd>{{ $periodLabel }} (Asia/Manila)</dd></div>
            <div><dt>Taxable sales</dt><dd>{{ $money($summary['taxable_sales']) }}</dd></div>
            <div><dt>Output VAT</dt><dd>{{ $money($summary['output_vat']) }}</dd></div>
            <div><dt>Total sales including VAT</dt><dd>{{ $money($summary['total_sales']) }}</dd></div>
            <div><dt>Input VAT status</dt><dd>Not captured</dd></div>
            <div><dt>Deductions applied</dt><dd>{{ $money($summary['deductions_applied']) }}</dd></div>
            <div><dt>POS-only VAT payable estimate</dt><dd>{{ $money($summary['vat_payable_estimate']) }}</dd></div>
        </dl>
    </section>
</section>
