<section class="trial-balance-page" aria-labelledby="trial-balance-heading">
    <header class="page-heading trial-balance-heading">
        <div>
            <h1 id="trial-balance-heading">Trial Balance</h1>
            <p class="page-subtitle">Cumulative account balances through the selected date, with period movement and schedule reconciliation.</p>
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
            <p class="trial-balance-report-eyebrow">As-of trial balance</p>
            <h2 id="trial-balance-report-heading">Trial Balance</h2>
            <p>Movement: {{ $fromDate !== '' ? $fromDate : 'Not selected' }} through {{ $toDate !== '' ? $toDate : 'Not selected' }} (inclusive, Manila time)</p>
            <p>Balances as of: {{ $toDate !== '' ? $toDate : 'Not selected' }}</p>
            @if ($cutoverDate)
                <p>Supported accounting coverage begins {{ $cutoverDate }}.</p>
            @endif
        </header>

        @if ($dateError)
            <div class="trial-balance-empty" role="alert">
                <p>Trial Balance unavailable</p>
                <span>{{ $dateError }}</span>
            </div>
        @else
            <div class="trial-balance-totals" aria-label="Trial balance totals">
                <div><span>Closing debits</span><strong>{{ number_format($totalDebitsCents / 100, 2) }}</strong></div>
                <div><span>Closing credits</span><strong>{{ number_format($totalCreditsCents / 100, 2) }}</strong></div>
                <div><span>Period debits</span><strong>{{ number_format($periodDebitsCents / 100, 2) }}</strong></div>
                <div><span>Period credits</span><strong>{{ number_format($periodCreditsCents / 100, 2) }}</strong></div>
            </div>

            <div class="trial-balance-status {{ $isBalanced ? 'is-balanced' : 'has-accounting-error' }}" role="status">
                @if ($isBalanced)
                    Books balance: total closing debits equal total closing credits.
                @else
                    Accounting error: total closing debits do not equal total closing credits. No balancing entry has been added.
                @endif
            </div>

            @if ($accounts->isEmpty())
                <div class="trial-balance-empty" role="status">
                    <p>Activated empty book</p>
                    <span>The approved opening journal activates this book. No accounts are currently available to display.</span>
                </div>
            @else
                @if (! $hasActivity)
                    <p class="trial-balance-no-activity" role="status">No activity during this period; opening balances are retained.</p>
                @endif
                <div class="trial-balance-table-wrap">
                    <table class="trial-balance-table">
                        <thead>
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col">Opening debit</th>
                                <th scope="col">Opening credit</th>
                                <th scope="col">Period debit</th>
                                <th scope="col">Period credit</th>
                                <th scope="col">Closing debit</th>
                                <th scope="col">Closing credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $row)
                                <tr>
                                    <th scope="row"><span>{{ $row['account']->code }}</span> {{ $row['account']->name }}@if (! $row['account']->is_active) <span class="trial-balance-inactive">Inactive</span>@endif</th>
                                    <td>{{ number_format($row['opening_debit_cents'] / 100, 2) }}</td>
                                    <td>{{ number_format($row['opening_credit_cents'] / 100, 2) }}</td>
                                    <td>{{ number_format($row['period_debit_cents'] / 100, 2) }}</td>
                                    <td>{{ number_format($row['period_credit_cents'] / 100, 2) }}</td>
                                    <td>{{ number_format($row['closing_debit_cents'] / 100, 2) }}</td>
                                    <td>{{ number_format($row['closing_credit_cents'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <section class="trial-balance-reconciliation" aria-labelledby="trial-balance-reconciliation-heading">
                <h3 id="trial-balance-reconciliation-heading">Supporting schedule reconciliation as of {{ $toDate }}</h3>
                @if ($apScheduleCents !== null)
                    <p class="{{ $apMismatch ? 'schedule-mismatch' : 'schedule-matched' }}">
                        Accounts Payable: book {{ number_format($apBookCents / 100, 2) }}; supplier and invoice schedule {{ number_format($apScheduleCents / 100, 2) }}.
                        {{ $apMismatch ? 'Schedule mismatch.' : 'Reconciled.' }}
                    </p>
                @endif
                @if ($inventoryScheduleCents !== null)
                    <p class="{{ $inventoryMismatch ? 'schedule-mismatch' : 'schedule-matched' }}">
                        Inventory: book {{ number_format($inventoryBookCents / 100, 2) }}; valued-item schedule {{ number_format($inventoryScheduleCents / 100, 2) }}.
                        {{ $inventoryMismatch ? 'Schedule mismatch.' : 'Reconciled.' }}
                    </p>
                @elseif ($inventoryCoverageError)
                    <p class="schedule-mismatch">Inventory schedule unavailable: {{ $inventoryCoverageError }}</p>
                @endif
                @if ($apScheduleCents === null && $inventoryScheduleCents === null && ! $inventoryCoverageError)
                    <p>No Accounts Payable or Inventory control account is configured for schedule reconciliation.</p>
                @endif
            </section>
        @endif
    </article>
</section>
