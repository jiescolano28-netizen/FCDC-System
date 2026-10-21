@php($money = $money ?? fn ($amount) => '₱'.number_format((float) $amount, 2))
<section class="dashboard-stats vat-summary-cards" aria-label="{{ $ariaLabel ?? 'VAT summary totals' }}">
    <article class="dashboard-card vat-summary-card">
        <p class="dashboard-stat-label">VATable sales</p>
        <p class="dashboard-stat-value">{{ $money($summary['taxable_sales']) }}</p>
        <p class="vat-summary-card-note">Standard-rated sales</p>
    </article>
    <article class="dashboard-card vat-summary-card">
        <p class="dashboard-stat-label">Zero-rated sales</p>
        <p class="dashboard-stat-value">{{ $money($summary['zero_rated_sales']) }}</p>
        <p class="vat-summary-card-note">0% VAT sales</p>
    </article>
    <article class="dashboard-card vat-summary-card">
        <p class="dashboard-stat-label">VAT-exempt sales</p>
        <p class="dashboard-stat-value">{{ $money($summary['exempt_sales']) }}</p>
        <p class="vat-summary-card-note">Exempt transactions</p>
    </article>
    <article class="dashboard-card vat-summary-card">
        <p class="dashboard-stat-label">Non-VAT / outside scope sales</p>
        <p class="dashboard-stat-value">{{ $money($summary['non_vat_sales']) }}</p>
        <p class="vat-summary-card-note">Outside VAT scope</p>
    </article>
    <article class="dashboard-card vat-summary-card is-output">
        <p class="dashboard-stat-label">Output VAT</p>
        <p class="dashboard-stat-value">{{ $money($summary['output_vat']) }}</p>
        <p class="vat-summary-card-note">From completed POS sales</p>
    </article>
    <article class="dashboard-card vat-summary-card is-input">
        <p class="dashboard-stat-label">Input VAT</p>
        <p class="dashboard-stat-value">{{ $money($summary['input_vat']) }}</p>
        <p class="vat-summary-card-note">
            {{ $summary['input_vat_status'] === 'captured'
                ? $summary['input_vat_record_count'].' saved reworking-cost record(s)'
                : 'Not captured' }}
        </p>
    </article>
    <article class="dashboard-card vat-summary-card is-payable">
        <p class="dashboard-stat-label">Estimated VAT payable</p>
        <p class="dashboard-stat-value">{{ $money($summary['vat_payable_estimate']) }}</p>
        <p class="vat-summary-card-note">Output VAT less recorded input VAT</p>
    </article>
</section>
