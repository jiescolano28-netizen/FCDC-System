<section class="general-ledger-page" aria-labelledby="general-ledger-heading">
    <header class="page-heading general-ledger-heading">
        <div>
            <h1 id="general-ledger-heading">General Ledger</h1>
            <p class="page-subtitle">Ledger reports are not yet connected to posted manual journal lines.</p>
        </div>
    </header>

    <p class="temporary-notice" role="status">Manual journal entries can be posted and browsed in Journal Entry. This page does not yet display ledger activity or calculate running balances.</p>

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
                <p>Posted journal entries are available in the Journal Entry register.</p>
            </div>
        </header>
        <div class="general-ledger-empty" role="status">
            <p>General Ledger reporting is not available yet.</p>
            <span>Posted manual journal lines and running balances are not displayed on this page.</span>
        </div>
    </section>
</section>
