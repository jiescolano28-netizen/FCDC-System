@php($money = fn ($amount) => '₱'.number_format($amount, 2))
<section class="vat-page vat-return-page" aria-labelledby="vat-return-heading">
    <header class="vat-page-heading">
        <div>
            <h1 id="vat-return-heading">VAT Return Preparation</h1>
            <p class="page-subtitle">Illustrative quarterly preparation and printing interface. Not a complete or official Form 2550Q.</p>
        </div>
        <span class="vat-rate-badge">Illustrative 12% VAT</span>
    </header>

    <p class="temporary-notice" role="note">Demonstration only: this illustrative preparation interface is not a complete or official Form 2550Q and is not a filing or submission. It does not replace eFPS, eBIRForms, or any official BIR filing system. Values are fixtures, not actual taxable activity; POS demonstration sales are not included.</p>

    <div class="vat-return-controls">
        <label>
            <span>Reporting period</span>
            <select wire:model.live="selectedPeriod">
                @foreach ($periods as $period)
                    <option value="{{ $period }}">{{ $period }}</option>
                @endforeach
            </select>
        </label>
        <button class="vat-detail-button" type="button" onclick="document.body.classList.add('printing-vat-return'); window.print(); setTimeout(() => document.body.classList.remove('printing-vat-return'), 100)">Print preparation document</button>
    </div>

    <article id="vat-return-print" class="vat-return-document" aria-label="Illustrative VAT return preparation document">
        <header class="vat-return-document-heading">
            <h2>Illustrative quarterly VAT return preparation</h2>
            <p>Form 2550Q-style demonstration document · For review / printing</p>
        </header>

        <section aria-labelledby="vat-return-taxpayer-heading">
            <h3 id="vat-return-taxpayer-heading">Taxpayer / Company Information</h3>
            <div class="vat-return-fields">
                <label class="settings-field">
                    <span>Taxpayer / Company Name</span>
                    <input type="text" wire:model.live="companyName" value="{{ $companyName }}" autocomplete="organization">
                </label>
                <div class="settings-field">
                    <span>Tax Period</span>
                    <output>{{ $selectedPeriod }}</output>
                </div>
                <div class="settings-field">
                    <span>VAT Rate</span>
                    <output>12% illustrative</output>
                </div>
                <div class="settings-field">
                    <span>Preparation Status</span>
                    <output>For review / printing</output>
                </div>
            </div>
        </section>

        <section aria-labelledby="vat-return-totals-heading">
            <h3 id="vat-return-totals-heading">Illustrative VAT-related Sales Information</h3>
            @if ($summary)
                <div class="vat-table-wrap">
                    <table>
                        <thead><tr><th>Information</th><th class="numeric">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Taxable Sales</td><td class="numeric">{{ $money($summary['taxable']) }}</td></tr>
                            <tr><td>VAT Rate</td><td class="numeric">12% illustrative</td></tr>
                            <tr><td>VAT Amount</td><td class="numeric">{{ $money($summary['vat']) }}</td></tr>
                            <tr><td><strong>Total Sales</strong></td><td class="numeric"><strong>{{ $money($summary['total']) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <p class="vat-return-disclaimer">Illustrative preparation interface only. This is not a complete or official Form 2550Q, is not a filing or submission, and does not replace eFPS, eBIRForms, or any official BIR filing system.</p>
    </article>
</section>
