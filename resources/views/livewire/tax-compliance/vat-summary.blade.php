@php($money = fn ($amount) => '₱'.number_format($amount, 2))
<section class="vat-page" aria-labelledby="vat-summary-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-summary-heading">VAT Summary</h1>
            <p class="page-subtitle">Illustrative internal VAT summary only. These values are not actual taxable activity or a filed return.</p>
        </div>
        <span class="vat-rate-badge">Illustrative 12% VAT</span>
    </header>

    <p class="temporary-notice" role="note">Demonstration only: these placeholder totals are not evidence of actual taxable activity and do not constitute a statutory return. Nothing is filed with a tax authority. POS demonstration sales are not included.</p>

    <div class="vat-summary-controls">
        <label>
            <span>Reporting period</span>
            <select wire:model.live="periodType">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
            </select>
        </label>
        <label>
            <span>Period</span>
            <select wire:model.live="selectedPeriod">
                @foreach ($periods as $period)
                    <option value="{{ $period }}">{{ $period }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($summary)
        <section class="dashboard-stats reports-stats vat-summary-stats" aria-label="{{ $selectedPeriod }} illustrative VAT totals">
            <article class="dashboard-card">
                <p class="dashboard-stat-label">Taxable sales</p>
                <p class="dashboard-stat-value">{{ $money($summary['taxable']) }}</p>
            </article>
            <article class="dashboard-card">
                <p class="dashboard-stat-label">VAT amount</p>
                <p class="dashboard-stat-value">{{ $money($summary['vat']) }}</p>
            </article>
            <article class="dashboard-card">
                <p class="dashboard-stat-label">Total sales</p>
                <p class="dashboard-stat-value">{{ $money($summary['total']) }}</p>
            </article>
        </section>
    @endif

    <section class="dashboard-card vat-summary-records">
        <div class="dashboard-card-heading">
            <h2>Illustrative reporting totals</h2>
            <a class="vat-detail-button" href="{{ route('tax.vat-records') }}">View VAT Records</a>
        </div>
        <p class="dashboard-chart-note">Summary values use the established VAT demonstration fixtures and are separate from recorded business activity.</p>
        @if ($summary)
            <dl class="vat-summary-totals">
                <div><dt>Selected period</dt><dd>{{ $selectedPeriod }}</dd></div>
                <div><dt>Total taxable sales</dt><dd>{{ $money($summary['taxable']) }}</dd></div>
                <div><dt>Total VAT</dt><dd>{{ $money($summary['vat']) }}</dd></div>
                <div><dt>Total sales including VAT</dt><dd>{{ $money($summary['total']) }}</dd></div>
            </dl>
        @endif
    </section>
</section>
