<section class="accounting-page" aria-labelledby="accounting-periods-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="accounting-periods-heading">Accounting Periods</h1>
            <p class="dashboard-subtitle">Manila calendar-month readiness, close and explicitly authorized reopen history.</p>
        </div>
        <label>Fiscal year <select wire:model.live="year">@foreach ($availableYears as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
    </header>

    @if (session()->has('period-message'))<p role="status">{{ session('period-message') }}</p>@endif
    @if ($errors->any())<div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="dashboard-card accounting-activity" aria-label="Monthly accounting periods">
        <div class="reports-table-wrap"><table class="reports-table">
            <thead><tr><th>Month</th><th>State</th><th>Readiness</th><th>Close / reopen history</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach ($months as $row)
                    @php($period = $row['period'])
                    @php($readiness = $row['readiness'])
                    <tr wire:key="accounting-period-{{ $period->id }}">
                        <th scope="row">{{ $period->starts_on->format('F Y') }}</th>
                        <td>{{ ucfirst($period->status) }}</td>
                        <td>
                            @if ($readiness['ready'])<span>Ready: books, schedules and accounting equation reconcile.</span>
                            @else<ul>@foreach ($readiness['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>@endif
                        </td>
                        <td>
                            @if ($period->closed_at)Closed {{ $period->closed_at->timezone('UTC')->format('Y-m-d H:i:s') }} UTC by {{ $period->closer?->username ?? 'Unavailable' }} — {{ $period->close_reason }}@endif
                            @if ($period->reopened_at)<br>Reopened {{ $period->reopened_at->timezone('UTC')->format('Y-m-d H:i:s') }} UTC by {{ $period->reopener?->username ?? 'Unavailable' }} — {{ $period->reopen_reason }}@endif
                        </td>
                        <td>
                            @if ($period->status === 'open')
                                @can('accounting.close-period')
                                    <form wire:submit="close({{ $period->id }})">
                                        <label>Close reason <textarea wire:model="reason" maxlength="4000" required></textarea></label>
                                        <button type="submit" @disabled(! $readiness['ready'] || $period->ends_on->toDateString() >= now('Asia/Manila')->toDateString())>Close month</button>
                                    </form>
                                @endcan
                            @else
                                @can('accounting.reopen-period')<button type="button" wire:click="selectForReopen({{ $period->id }})">Reopen month</button>@endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="chart-account-card" aria-labelledby="fiscal-close-heading">
        <h2 id="fiscal-close-heading">Fiscal year {{ $year }} closing</h2>
        @forelse ($fiscalClosings as $closing)
            @php($reversal = $closing->corrections->first())
            <p>
                {{ $reversal ? 'Reversed' : 'Fiscal year closed' }}:
                <a href="{{ route('accounting.general-ledger', ['accountId' => $closing->lines->firstWhere('account.classification', 'retained_earnings')?->accounting_account_id, 'fromDate' => $closing->accounting_date->toDateString(), 'toDate' => $reversal?->accounting_date?->toDateString() ?? $closing->accounting_date->toDateString()]) }}">{{ $closing->reference }}</a>
                — {{ $closing->description }}
                @if ($reversal)
                    <br>Reversal <a href="{{ route('accounting.general-ledger', ['accountId' => $reversal->lines->firstWhere('accounting_account_id', $closing->lines->firstWhere('account.classification', 'retained_earnings')?->accounting_account_id)?->accounting_account_id, 'fromDate' => $reversal->accounting_date->toDateString(), 'toDate' => $reversal->accounting_date->toDateString()]) }}">{{ $reversal->reference }}</a>
                    — {{ $reversal->correction_reason }}
                @endif
            </p>
        @empty
            <p>No fiscal closing entry is recorded for this year.</p>
        @endforelse
        @if ($allMonthsClosed && ! $hasActiveFiscalClose && auth()->user()?->can('accounting.close-fiscal-year'))
            <form wire:submit="closeFiscalYear">
                <label>Approved retained earnings account
                    <select wire:model="retainedEarningsAccountId" required>
                        <option value="">Select account</option>
                        @foreach ($retainedEarningsAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Fiscal close reason <textarea wire:model="fiscalCloseReason" maxlength="4000" required></textarea></label>
                <button type="submit" @disabled($retainedEarningsAccounts->isEmpty())>Close fiscal year</button>
            </form>
        @elseif (! $allMonthsClosed)
            <p>Close and reconcile every month before the fiscal-year transfer is available.</p>
        @elseif ($hasActiveFiscalClose)
            <p role="status">Fiscal year closed. Reopen reverses its linked entry before corrections.</p>
        @endif
    </section>

    @if ($selectedPeriod)
        <section class="chart-account-card" aria-labelledby="reopen-period-heading">
            <h2 id="reopen-period-heading">Reopen {{ $selectedPeriod->starts_on->format('F Y') }}</h2>
            <p>Later closed periods depend on this period. Every listed period must be explicitly selected. They become open together; reconcile and reclose chronologically.</p>
            @if ($dependentPeriods->isEmpty())<p>No later closed periods depend on this month.</p>@endif
            @foreach ($dependentPeriods as $dependent)
                <label><input type="checkbox" wire:model="dependentPeriodIds" value="{{ $dependent->id }}"> Authorize {{ $dependent->starts_on->format('F Y') }}</label>
            @endforeach
            <form wire:submit="reopen({{ $selectedPeriod->id }})">
                <label>Reopen reason <textarea wire:model="reason" maxlength="4000" required></textarea></label>
                <button type="submit">Reopen selected chain</button>
            </form>
        </section>
    @endif
</section>
