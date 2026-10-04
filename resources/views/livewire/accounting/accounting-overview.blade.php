<section class="accounting-page" id="accounting-overview">
    <header class="dashboard-heading">
        <div>
            <h1>Accounting Overview</h1>
            <p class="dashboard-subtitle">Accounting demonstrations and unavailable reporting destinations.</p>
        </div>
    </header>

    <p class="temporary-notice" role="status">
        Real accounting balances and reports are not implemented. This page does not calculate or verify company revenue, expenses, net income, payables, or posted activity.
    </p>

    <section class="accounting-balance-grid" aria-label="Accounting balances">
        @foreach (['Revenue', 'Expenses', 'Net income', 'Accounts payable'] as $balance)
            <article class="dashboard-card">
                <p class="dashboard-stat-label">{{ $balance }}</p>
                <p class="accounting-unavailable">Not available</p>
                <p class="dashboard-stat-note">No verified balance is provided.</p>
            </article>
        @endforeach
    </section>

    <section class="dashboard-card accounting-activity" aria-labelledby="accounting-activity-title">
        <h2 class="dashboard-card-title" id="accounting-activity-title">Recent accounting entries</h2>
        <p class="dashboard-chart-note">Demo journal sessions and POS sales are not accounting postings and are not included here.</p>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Description</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="reports-empty" colspan="4">No posted accounting activity. Real accounting posting is not implemented.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="dashboard-card accounting-actions" aria-labelledby="accounting-actions-title">
        <h2 class="dashboard-card-title" id="accounting-actions-title">Accounting quick actions</h2>
        <p class="dashboard-chart-note">Quick actions are enabled only when their separate authenticated pages are available.</p>
        <div class="accounting-action-grid">
            @php($quickActions = [
                'Journal Entry' => 'accounting.journal-entry',
                'General Ledger' => 'accounting.general-ledger',
                'Trial Balance' => 'accounting.trial-balance',
                'Financial Statements' => 'accounting.financial-statements',
            ])
            @foreach ($quickActions as $label => $routeName)
                @if (\Illuminate\Support\Facades\Route::has($routeName))
                    <a class="accounting-action" href="{{ route($routeName) }}">
                        <span>{{ $label }}</span>
                        <span class="accounting-action-status">Open page</span>
                    </a>
                @else
                    <button class="accounting-action" type="button" disabled>
                        <span>{{ $label }}</span>
                        <span class="accounting-action-status">Unavailable</span>
                    </button>
                @endif
            @endforeach
        </div>
    </section>
</section>
