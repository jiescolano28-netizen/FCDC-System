<section class="trial-balance-page" aria-labelledby="trial-balance-heading">
    <header class="page-heading trial-balance-heading">
        <div>
            <h1 id="trial-balance-heading">Trial Balance</h1>
            <p class="page-subtitle">Unadjusted and adjusted account balances through the selected date, with period movement, adjusting entries, and schedule reconciliation.</p>
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
            @if ($openingWarning)
                <p class="trial-balance-status has-accounting-error" role="status">{{ $openingWarning }}</p>
            @endif
        </header>

        @if ($dateError)
            <div class="trial-balance-empty" role="alert">
                <p>Trial Balance unavailable</p>
                <span>{{ $dateError }}</span>
            </div>
        @else
            <div class="trial-balance-totals" aria-label="Trial balance totals">
                <div class="trial-balance-total-group"><h3>Unadjusted</h3><span>Unadjusted debits</span><strong>₱{{ number_format($unadjustedDebitsCents / 100, 2) }}</strong><span>Unadjusted credits</span><strong>₱{{ number_format($unadjustedCreditsCents / 100, 2) }}</strong></div>
                <div class="trial-balance-total-group"><h3>Adjustments</h3><span>Adjustments debit</span><strong>₱{{ number_format($adjustmentDebitsCents / 100, 2) }}</strong><span>Adjustments credit</span><strong>₱{{ number_format($adjustmentCreditsCents / 100, 2) }}</strong></div>
                <div class="trial-balance-total-group trial-balance-total-final"><h3>Adjusted</h3><span>Adjusted debits</span><strong>₱{{ number_format($totalDebitsCents / 100, 2) }}</strong><span>Adjusted credits</span><strong>₱{{ number_format($totalCreditsCents / 100, 2) }}</strong></div>
                <div class="trial-balance-total-group"><h3>Period activity <small>(standard)</small></h3><span>Period debits</span><strong>₱{{ number_format($periodDebitsCents / 100, 2) }}</strong><span>Period credits</span><strong>₱{{ number_format($periodCreditsCents / 100, 2) }}</strong></div>
            </div>

            <div class="trial-balance-status {{ $isAdjustedBalanced ? 'is-balanced' : 'has-accounting-error' }}" role="status">
                @if ($isAdjustedBalanced)
                    Books balance: total adjusted debits equal total adjusted credits.
                @else
                    Accounting error: total adjusted debits do not equal total adjusted credits. No balancing entry has been added.
                @endif
            </div>
            @if (! $isUnadjustedBalanced)
                <div class="trial-balance-status has-accounting-error" role="status">
                    Unadjusted trial balance is out of balance by {{ number_format($unadjustedDifferenceCents / 100, 2) }}.
                    Review and post authorized adjusting entries to correct the affected accounts.
                </div>
            @endif
            <p class="trial-balance-basis-note">
                Adjusting entries are approved, referenced, and explained journals dated within the selected period.
                The adjusted trial balance is the basis for the financial statements.
            </p>

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
                    <table class="trial-balance-table" aria-label="Trial balance by account">
                        <colgroup><col class="trial-balance-account-column"><col span="10" class="trial-balance-amount-column"></colgroup>
                        <thead>
                            <tr>
                                <th id="tb-account-heading" scope="col" rowspan="2">Account</th>
                                <th id="tb-opening-group" scope="colgroup" colspan="2">Opening balance</th>
                                <th id="tb-period-group" scope="colgroup" colspan="2">Period activity*</th>
                                <th id="tb-unadjusted-group" scope="colgroup" colspan="2">Unadjusted balance</th>
                                <th id="tb-adjustments-group" scope="colgroup" colspan="2">Adjustments</th>
                                <th id="tb-adjusted-group" scope="colgroup" colspan="2">Adjusted balance</th>
                            </tr>
                            <tr>
                                <th id="tb-opening-debit" scope="col" headers="tb-opening-group">Debit</th><th id="tb-opening-credit" scope="col" headers="tb-opening-group">Credit</th>
                                <th id="tb-period-debit" scope="col" headers="tb-period-group">Debit</th><th id="tb-period-credit" scope="col" headers="tb-period-group">Credit</th>
                                <th id="tb-unadjusted-debit" scope="col" headers="tb-unadjusted-group">Debit</th><th id="tb-unadjusted-credit" scope="col" headers="tb-unadjusted-group">Credit</th>
                                <th id="tb-adjustment-debit" scope="col" headers="tb-adjustments-group">Debit</th><th id="tb-adjustment-credit" scope="col" headers="tb-adjustments-group">Credit</th>
                                <th id="tb-adjusted-debit" scope="col" headers="tb-adjusted-group">Debit</th><th id="tb-adjusted-credit" scope="col" headers="tb-adjusted-group">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $row)
                                <tr>
                                    <th id="tb-account-{{ $row['account']->id }}" scope="row"><span>{{ $row['account']->code }}</span> {{ $row['account']->name }}@if (! $row['account']->is_active) <span class="trial-balance-inactive">Inactive</span>@endif</th>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-opening-debit">{{ number_format($row['opening_debit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-opening-credit">{{ number_format($row['opening_credit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-period-debit">{{ number_format($row['period_debit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-period-credit">{{ number_format($row['period_credit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-unadjusted-debit">{{ number_format($row['unadjusted_debit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-unadjusted-credit">{{ number_format($row['unadjusted_credit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-adjustment-debit">{{ number_format($row['adjustment_debit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-adjustment-credit">{{ number_format($row['adjustment_credit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-adjusted-debit">{{ number_format($row['closing_debit_cents'] / 100, 2) }}</td>
                                    <td headers="tb-account-{{ $row['account']->id }} tb-adjusted-credit">{{ number_format($row['closing_credit_cents'] / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="trial-balance-table-note">* Standard journal activity only. Adjusting entries are shown separately.</p>
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
