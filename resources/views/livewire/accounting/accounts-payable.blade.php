<section class="accounting-page accounts-payable-page" aria-labelledby="accounts-payable-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="accounts-payable-heading">Accounts Payable</h1>
            <p class="dashboard-subtitle">Supplier invoice demonstration; real payable management is unavailable.</p>
        </div>
        <button class="accounting-action payable-add-button" type="button" disabled aria-describedby="payable-maintenance-note">Add Payable</button>
    </header>

    <p class="temporary-notice" role="status">
        No verified payable balances or obligations are available. This demonstration does not track supplier invoices, payments, or accounting postings.
    </p>

    <section class="accounting-balance-grid" aria-label="Payable status">
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Payable amount</p>
            <p class="accounting-unavailable">Amount unavailable</p>
            <p class="dashboard-stat-note">No verified company obligation is provided.</p>
        </article>
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Open invoices</p>
            <p class="accounting-unavailable">Open invoices unavailable</p>
            <p class="dashboard-stat-note">Invoice counts and balances are not tracked.</p>
        </article>
    </section>

    <p class="payable-action-note" id="payable-maintenance-note">Payable creation, editing, and payment actions are unavailable in this demonstration.</p>

    <section class="dashboard-card accounting-activity" aria-labelledby="payable-invoices-heading">
        <header class="dashboard-card-heading">
            <div>
                <h2 id="payable-invoices-heading">Supplier invoices</h2>
                <p class="dashboard-chart-note">No supplier or invoice records are stored or demonstrated.</p>
            </div>
        </header>

        <div class="payable-filters" aria-label="Filter supplier invoices">
            <label>
                <span>Search supplier or invoice</span>
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search supplier or invoice number">
            </label>
            <label>
                <span>Payment status</span>
                <select wire:model.live="status">
                    <option value="All">All statuses</option>
                    <option value="Unpaid">Unpaid</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Paid">Paid</option>
                </select>
            </label>
        </div>

        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th scope="col">Supplier</th>
                        <th scope="col">Invoice</th>
                        <th scope="col">Invoice date</th>
                        <th scope="col">Due date</th>
                        <th scope="col" class="numeric">Amount</th>
                        <th scope="col" class="numeric">Open amount</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="payable-invoice-{{ $invoice['id'] }}">
                            <td>{{ $invoice['supplier'] }}</td>
                            <td>{{ $invoice['number'] }}</td>
                            <td>{{ $invoice['date'] }}</td>
                            <td>{{ $invoice['due_date'] }}</td>
                            <td class="numeric">{{ $invoice['amount'] }}</td>
                            <td class="numeric">{{ $invoice['open_amount'] }}</td>
                            <td>{{ $invoice['status'] }}</td>
                            <td>
                                <button type="button" disabled aria-describedby="payable-maintenance-note">Unavailable</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="reports-empty payable-empty" colspan="8">No supplier invoices are available in this demonstration. Filters do not create or imply real payable records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
