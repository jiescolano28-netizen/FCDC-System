<section class="chart-of-accounts-page" aria-labelledby="chart-of-accounts-heading">
    <header class="page-heading chart-of-accounts-heading">
        <div>
            <h1 id="chart-of-accounts-heading">Chart of Accounts</h1>
            <p class="page-subtitle">Illustrative sample accounts for interface demonstration; this is not an approved production chart.</p>
        </div>
        <button class="chart-account-action" type="button" disabled aria-describedby="account-maintenance-note">Add Account</button>
    </header>

    <p class="temporary-notice" role="note">Illustrative reference data only. These sample accounts are not approved for production, are not database account records, and do not authorize account maintenance or accounting activity.</p>
    <p class="chart-account-action-note" id="account-maintenance-note">Account creation and editing are unavailable in this demonstration.</p>

    <div class="chart-account-filters" aria-label="Filter sample accounts">
        <label>
            <span>Search code or name</span>
            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search account code or name">
        </label>
        <label>
            <span>Account type</span>
            <select wire:model.live="accountType">
                <option value="All">All types</option>
                <option value="Asset">Asset</option>
                <option value="Liability">Liability</option>
                <option value="Equity">Equity</option>
                <option value="Revenue">Revenue</option>
                <option value="Expense">Expense</option>
            </select>
        </label>
        <label>
            <span>Status</span>
            <select wire:model.live="status">
                <option value="All">All statuses</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </label>
    </div>

    <section class="chart-account-card" aria-labelledby="sample-accounts-heading">
        <header>
            <div>
                <h2 id="sample-accounts-heading">Sample reference accounts</h2>
                <p>All values are illustrative and separate from persisted business data.</p>
            </div>
            <span class="chart-account-count">Showing {{ $accounts->count() }} of {{ $accountCount }} samples</span>
        </header>
        <div class="chart-account-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Account Code</th>
                        <th scope="col">Account Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Description</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr wire:key="reference-account-{{ $account['code'] }}">
                            <td><strong>{{ $account['code'] }}</strong></td>
                            <td>{{ $account['name'] }}</td>
                            <td>{{ $account['type'] }}</td>
                            <td>{{ $account['description'] }}</td>
                            <td><span class="chart-account-status">{{ $account['status'] }}</span></td>
                            <td><button class="chart-account-edit" type="button" disabled aria-describedby="account-maintenance-note">Edit</button></td>
                        </tr>
                    @empty
                        <tr><td class="chart-account-empty" colspan="6">No sample accounts match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
