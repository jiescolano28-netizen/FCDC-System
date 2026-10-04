<section class="trial-balance-page" aria-labelledby="trial-balance-heading">
    <header class="page-heading trial-balance-heading">
        <div>
            <h1 id="trial-balance-heading">Trial Balance</h1>
            <p class="page-subtitle">Choose a period to view the trial balance report demonstration.</p>
        </div>
        <button class="vat-detail-button" type="button" onclick="window.print()">Print report</button>
    </header>

    <div class="trial-balance-period-controls" role="group" aria-label="Trial balance period">
        <label>
            <span>From date</span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </div>

    <article id="trial-balance-report" class="trial-balance-report" aria-labelledby="trial-balance-report-heading">
        <header class="trial-balance-report-heading">
            <p class="trial-balance-report-eyebrow">Accounting report demonstration</p>
            <h2 id="trial-balance-report-heading">Trial Balance</h2>
            <p>From: {{ $fromDate !== '' ? $fromDate : 'Not selected' }}</p>
            <p>To: {{ $toDate !== '' ? $toDate : 'Not selected' }}</p>
        </header>

        <div class="trial-balance-totals" aria-label="Trial balance totals">
            <div><span>Total debits</span><strong>Total debits unavailable</strong></div>
            <div><span>Total credits</span><strong>Total credits unavailable</strong></div>
        </div>

        <div class="trial-balance-empty" role="status">
            <p>No trial balance is available.</p>
            <span>This is not a real trial balance and does not certify that company books are balanced.</span>
        </div>
    </article>
</section>
