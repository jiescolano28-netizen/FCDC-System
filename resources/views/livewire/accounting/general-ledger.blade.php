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
                <option value="">Select an approved account</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}{{ $account->is_active ? '' : ' (Inactive)' }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>From date</span>
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
                    <p>{{ $fromDate }} through {{ $toDate }} (inclusive, Manila dates){{ $selectedAccount->is_active ? '' : ' · Inactive account with posted history' }}</p>
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
                                            <br><a href="{{ $sourceUrl }}">View source detail</a>
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
        <p class="general-ledger-empty" role="status">Select an approved account to view its posted history.</p>
    @endif
</section>
