<section class="accounting-page" id="accounting-overview">
    <header class="dashboard-heading">
        <div>
            <h1>Accounting Overview</h1>
            <p class="dashboard-subtitle">Manual journals are available; accounting reports remain unavailable.</p>
        </div>
    </header>

    <p class="temporary-notice" role="status">
        Manual journal posting is available. This page does not calculate verified company revenue, expenses, net income, payables, or balances from posted journal lines.
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
        <p class="dashboard-chart-note">Posted manual entries are available in Journal Entry; this overview does not yet summarize recent posting activity.</p>
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
                        <td class="reports-empty" colspan="4">Recent posted entries are not summarized on this page.</td>
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
