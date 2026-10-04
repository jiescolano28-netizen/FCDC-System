<section class="general-ledger-page" aria-labelledby="general-ledger-heading">
    <header class="page-heading general-ledger-heading">
        <div>
            <h1 id="general-ledger-heading">General Ledger</h1>
            <p class="page-subtitle">Select a reference account and date range to inspect the ledger demonstration.</p>
        </div>
    </header>

    <p class="temporary-notice" role="status">Real ledger calculation and reporting are not implemented. This page does not create ledger entries or running balances from demo journals or POS sales.</p>

    <div class="general-ledger-filters" role="group" aria-label="General ledger selectors">
        <label>
            <span>Account</span>
            <select wire:model.live="accountCode">
                <option value="All">All accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account['code'] }}">{{ $account['code'] }} — {{ $account['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>From date</span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </div>

    <section class="chart-account-card" aria-labelledby="general-ledger-activity-heading">
        <header>
            <div>
                <h2 id="general-ledger-activity-heading">Ledger activity</h2>
                <p>Reference accounts and selectors are illustrative; no posted ledger data is available.</p>
            </div>
        </header>
        <div class="general-ledger-empty" role="status">
            <p>No ledger entries are available.</p>
            <span>Real ledger calculation and reporting are not implemented.</span>
        </div>
    </section>
</section>
