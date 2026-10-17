<section class="general-ledger-page" aria-labelledby="general-ledger-heading">
    <header class="page-heading general-ledger-heading">
        <div>
            <h1 id="general-ledger-heading">General Ledger</h1>
            <p class="page-subtitle">Posted account activity from approved accounting cutover, with opening and running balances.</p>
        </div>
    </header>

    <div class="general-ledger-filters" role="group" aria-label="General ledger selectors">
        <label>
            <span>Account</span>
            <select wire:model.live="accountId">
                <option value="">All accounts</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}{{ $account->is_active ? '' : ' (Inactive)' }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>From date <small>(optional — defaults to cutover)</small></span>
            <input type="date" wire:model.live="fromDate">
        </label>
        <label>
            <span>To date</span>
            <input type="date" wire:model.live="toDate">
        </label>
    </div>

    @if ($cutoverDate)
        <p class="general-ledger-coverage">Supported transaction-level coverage begins {{ $cutoverDate }} (Manila).</p>
    @endif

    @if ($dateError)
        <p class="general-ledger-empty" role="status">{{ $dateError }}</p>
    @elseif ($selectedAccount)
        <section class="chart-account-card" aria-labelledby="general-ledger-activity-heading">
            <header>
                <div>
                    <h2 id="general-ledger-activity-heading">{{ $selectedAccount->code }} — {{ $selectedAccount->name }} ledger activity</h2>
                    <p>{{ $fromDate !== '' ? $fromDate : $cutoverDate }} through {{ $toDate }} (inclusive, Manila dates){{ $selectedAccount->is_active ? '' : ' · Inactive account with posted history' }}</p>
                </div>
            </header>
            <dl class="general-ledger-balances">
                <div><dt>Opening balance</dt><dd>{{ number_format(abs($openingBalanceCents) / 100, 2) }} {{ $openingBalanceCents < 0 ? 'Credit' : 'Debit' }}</dd></div>
                <div><dt>Closing balance</dt><dd>{{ number_format(abs($closingBalanceCents) / 100, 2) }} {{ $closingBalanceCents < 0 ? 'Credit' : 'Debit' }}</dd></div>
            </dl>
            @if ($lines->isEmpty())
                <div class="general-ledger-empty" role="status">
                    <p>No posted activity in this period.</p>
                    <span>The opening balance remains the closing balance.</span>
                </div>
            @else
                <div class="general-ledger-table-wrap">
                    <table class="general-ledger-table">
                        <thead><tr><th scope="col">Date</th><th scope="col">Journal / source reference</th><th scope="col">Description</th><th scope="col">Debit</th><th scope="col">Credit</th><th scope="col">Running balance</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr wire:key="general-ledger-line-{{ $line['journal']->id }}-{{ $loop->index }}">
                                    <td>{{ $line['journal']->accounting_date->format('Y-m-d') }}</td>
                                    <td>
                                        <a href="{{ $this->journalUrl($line['journal']) }}">{{ $line['journal']->reference }}</a>
                                        <span> · {{ $line['journal']->source_type }}:{{ $line['journal']->source_id }}</span>
                                        @if ($line['journal']->correctionOf)
                                            <span> · Correction of {{ $line['journal']->correctionOf->reference }}</span>
                                        @endif
                                         @if ($sourceUrl = $this->sourceUrl($line['journal']))
                                             <a class="general-ledger-source-link" href="{{ $sourceUrl }}">Source detail</a>
                                         @endif
                                    </td>
                                    <td>{{ $line['journal']->description }}@if ($line['description'])<br><span>{{ $line['description'] }}</span>@endif</td>
                                    <td>{{ $line['debit_cents'] ? number_format($line['debit_cents'] / 100, 2) : '—' }}</td>
                                    <td>{{ $line['credit_cents'] ? number_format($line['credit_cents'] / 100, 2) : '—' }}</td>
                                    <td>{{ number_format(abs($line['balance_cents']) / 100, 2) }} {{ $line['balance_cents'] < 0 ? 'Credit' : 'Debit' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        <section class="chart-account-card" aria-labelledby="general-ledger-activity-heading">
            <header><div><h2 id="general-ledger-activity-heading">All account activity</h2><p>{{ $fromDate !== '' ? $fromDate : $cutoverDate }} through {{ $toDate }} · inclusive Manila dates · {{ $allAccountRows->count() }} accounts with posted history</p></div></header>
            @if ($allAccountRows->isEmpty())
                <div class="general-ledger-empty" role="status"><p>No posted account activity for this date range.</p><span>Adjust the dates or choose a different account filter.</span></div>
            @else
                <div class="general-ledger-account-list">
                    @foreach ($allAccountRows as $accountRow)
                        <article class="general-ledger-account-group" wire:key="general-ledger-account-{{ $accountRow['account']->id }}">
                            <header class="general-ledger-account-group-heading">
                                <div><h3>{{ $accountRow['account']->code }} <span>{{ $accountRow['account']->name }}</span>@if (! $accountRow['account']->is_active)<small>Inactive</small>@endif</h3><p>{{ $accountRow['lines']->count() }} posted {{ $accountRow['lines']->count() === 1 ? 'entry' : 'entries' }} in selected period</p></div>
                                <dl>@if ($accountRow['opening'] !== 0)<div><dt>Opening balance</dt><dd>{{ number_format(abs($accountRow['opening']) / 100, 2) }} {{ $accountRow['opening'] < 0 ? 'Credit' : 'Debit' }}</dd></div>@endif<div><dt>Closing balance</dt><dd>{{ number_format(abs($accountRow['closing']) / 100, 2) }} {{ $accountRow['closing'] < 0 ? 'Credit' : 'Debit' }}</dd></div></dl>
                            </header>
                            @if ($accountRow['lines']->isEmpty())
                                <p class="general-ledger-account-no-activity">No posted entries in this period; the opening balance carries forward.</p>
                            @else
                                <div class="general-ledger-table-wrap">
                                    <table class="general-ledger-table general-ledger-account-table">
                                        <thead><tr><th scope="col">Date</th><th scope="col">Journal reference</th><th scope="col">Description</th><th scope="col">Debit</th><th scope="col">Credit</th><th scope="col">Running balance</th></tr></thead>
                                        <tbody>
                                            @foreach ($accountRow['lines'] as $line)
                                                <tr wire:key="general-ledger-all-{{ $line['journal']->id }}-{{ $loop->index }}">
                                                    <td>{{ $line['journal']->accounting_date->format('Y-m-d') }}</td>
                                                    <td><a href="{{ $this->journalUrl($line['journal']) }}">{{ $line['journal']->reference }}</a><span class="general-ledger-source">{{ $line['journal']->source_type }}:{{ $line['journal']->source_id }}</span>@if ($sourceUrl = $this->sourceUrl($line['journal']))<a class="general-ledger-source-link" href="{{ $sourceUrl }}">Source detail</a>@endif</td>
                                                    <td>{{ $line['journal']->description }}@if ($line['description'])<br><span>{{ $line['description'] }}</span>@endif</td>
                                                    <td>{{ $line['debit_cents'] ? number_format($line['debit_cents'] / 100, 2) : '—' }}</td><td>{{ $line['credit_cents'] ? number_format($line['credit_cents'] / 100, 2) : '—' }}</td>
                                                    <td>{{ number_format(abs($line['balance_cents']) / 100, 2) }} {{ $line['balance_cents'] < 0 ? 'Credit' : 'Debit' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</section>
