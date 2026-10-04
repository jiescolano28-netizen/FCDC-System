<section class="accounting-page cash-disbursements-page" aria-labelledby="cash-disbursements-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="cash-disbursements-heading">Cash Disbursements</h1>
            <p class="dashboard-subtitle">Payment activity demonstration; real cash disbursement management is unavailable.</p>
        </div>
        <button class="accounting-action cash-disbursement-add-button" type="button" disabled aria-describedby="cash-disbursement-maintenance-note">Add Disbursement</button>
    </header>

    <p class="temporary-notice" role="status">
        Payment processing and accounting posting are not implemented. This page does not record or verify cash movement or payment activity.
    </p>

    <section class="accounting-balance-grid" aria-label="Disbursement status">
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Disbursement total</p>
            <p class="accounting-unavailable">Payment totals unavailable</p>
            <p class="dashboard-stat-note">No verified payment totals are provided.</p>
        </article>
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Payment count</p>
            <p class="accounting-unavailable">Payment count unavailable</p>
            <p class="dashboard-stat-note">Disbursements are not recorded or counted.</p>
        </article>
    </section>

    <p class="payable-action-note" id="cash-disbursement-maintenance-note">Disbursement creation, editing, and payment processing are unavailable in this demonstration.</p>

    <section class="dashboard-card accounting-activity" aria-labelledby="cash-disbursement-records-heading">
        <header class="dashboard-card-heading">
            <div>
                <h2 id="cash-disbursement-records-heading">Disbursement records</h2>
                <p class="dashboard-chart-note">No payment records are stored or demonstrated.</p>
            </div>
        </header>

        <div class="payable-filters cash-disbursement-filters" aria-label="Filter disbursement records">
            <label>
                <span>Search reference or payee</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search reference or payee">
            </label>
            <label>
                <span>Payment method</span>
                <select wire:model.live="method">
                    <option value="All">All methods</option>
                    <option value="Cash">Cash</option>
                    <option value="Check">Check</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </label>
        </div>

        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Payee</th>
                        <th scope="col">Date</th>
                        <th scope="col">Payment method</th>
                        <th scope="col" class="numeric">Amount</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disbursements as $disbursement)
                        <tr wire:key="cash-disbursement-{{ $disbursement['id'] }}">
                            <td>{{ $disbursement['reference'] }}</td>
                            <td>{{ $disbursement['payee'] }}</td>
                            <td>{{ $disbursement['date'] }}</td>
                            <td>{{ $disbursement['method'] }}</td>
                            <td class="numeric">{{ $disbursement['amount'] }}</td>
                            <td>
                                <button type="button" disabled aria-describedby="cash-disbursement-maintenance-note">Unavailable</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="reports-empty payable-empty" colspan="6">No disbursement records are available. Filters do not create or imply payment or accounting activity.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
