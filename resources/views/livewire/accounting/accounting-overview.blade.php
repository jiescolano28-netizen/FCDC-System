<section class="accounting-page" id="accounting-overview">
    <header class="dashboard-heading">
        <div>
            <h1>Accounting Overview</h1>
            <p class="dashboard-subtitle">Period performance and closing balances from posted accounting records.</p>
        </div>
    </header>

    <section class="dashboard-card accounting-period" aria-label="Reporting period">
        <label>
            <span>From date</span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </section>

    @if (! $report['available'])
        <p class="temporary-notice" role="status">{{ $report['error'] }} Results are unavailable, not zero.</p>
    @else
        <p class="accounting-scope-note" role="status">
            Period {{ \Carbon\CarbonImmutable::parse($report['from'], 'Asia/Manila')->format('M j, Y') }}–{{ \Carbon\CarbonImmutable::parse($report['to'], 'Asia/Manila')->format('M j, Y') }} · amounts in PHP · accounting dates in Asia/Manila.
            @unless ($report['hasActivity']) No posted activity in this period; flow amounts are zero while balances carry forward as of period end. @endunless
        </p>
        <section class="accounting-balance-grid" aria-label="Accounting balances">
            @foreach ($report['cards'] as $card)
                <article class="dashboard-card">
                    <p class="dashboard-stat-label">{{ $card['label'] }}</p>
                    <p class="dashboard-stat-value">
                        @if ($card['amount'] === null)
                            Not available
                        @else
                            PHP {{ number_format($card['amount'] / 100, 2) }}
                        @endif
                    </p>
                    <p class="dashboard-stat-note">{{ $card['note'] }}</p>
                </article>
            @endforeach
        </section>
    @endif

    <section class="dashboard-card accounting-activity" aria-labelledby="accounting-activity-title">
        <h2 class="dashboard-card-title" id="accounting-activity-title">Recent posted journals</h2>
        @if (! $report['available'])
            <p class="dashboard-chart-note">Posted journal activity is unavailable until accounting coverage is approved.</p>
        @elseif ($report['journals']->isEmpty())
            <p class="dashboard-chart-note">No posted accounting journals are available.</p>
        @else
            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr><th scope="col">Posted date</th><th scope="col">Reference</th><th scope="col">Source</th><th scope="col">Description</th><th scope="col">Detail</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($report['journals'] as $journal)
                            <tr wire:key="overview-journal-{{ $journal->id }}">
                                <td>{{ $journal->accounting_date->format('Y-m-d') }}</td>
                                <td>{{ $journal->reference }}</td>
                                <td>{{ $journal->source_type }}</td>
                                <td>{{ $journal->description }}</td>
                                <td><a href="{{ route('accounting.journal-entry', ['journal' => $journal->id]) }}">View detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="dashboard-card accounting-actions" aria-labelledby="accounting-actions-title">
        <h2 class="dashboard-card-title" id="accounting-actions-title">Accounting quick actions</h2>
        <div class="accounting-action-grid">
            @php($quickActions = [
                'New Journal Entry' => 'accounting.journal-entry',
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
