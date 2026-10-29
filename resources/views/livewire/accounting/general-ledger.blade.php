<section class="general-ledger-page" aria-labelledby="general-ledger-heading">
    <header class="page-heading general-ledger-heading">
        <div class="general-ledger-heading-copy">
            <h1 id="general-ledger-heading">General Ledger</h1>
            <p class="page-subtitle">Posted account activity from the selected accounting book and cut-off date, with opening, movement and ending balances.</p>
        </div>
    </header>

    @if ($openingBalancesIncomplete || (! $productionActivated && $cutoverDate))
        <div class="general-ledger-notices">
            @if ($openingBalancesIncomplete)
                <p class="report-pre-activation-notice general-ledger-notice" role="status" data-testid="opening-balance-notice">
                    <strong>Opening balances are incomplete.</strong>
                    <span>The Opening Balance/Cutover journal is not yet approved for {{ $bookLabel }}. Posted transactions are shown for the selected dates, but unapproved opening balances are excluded. Full opening balance coverage is unavailable until an authorized reviewer posts the Opening Balance/Cutover journal; until then, opening balances are incomplete.</span>
                </p>
            @endif
            @if (! $productionActivated && $cutoverDate)
                <p class="report-pre-activation-notice general-ledger-notice" role="status" data-testid="pre-activation-notice">
                    <strong>Pre-activation preview.</strong>
                    <span>Opening balances are posted, but production accounting has not been activated. This ledger is provisional and may be replaced by final cutover activity. Trial Balance and Accounting Overview remain unavailable until an authorized reviewer activates production accounting.</span>
                </p>
            @endif
        </div>
    @endif

    <section class="general-ledger-card general-ledger-filters-panel" aria-labelledby="general-ledger-filters-heading">
        <header class="general-ledger-panel-heading">
            <div>
                <h2 id="general-ledger-filters-heading">Report filters</h2>
                <p>Choose a book and ledger view, then refine the inclusive Manila date range.</p>
            </div>
        </header>
        <div class="general-ledger-filters" role="group" aria-label="General ledger selectors">
            <label class="general-ledger-filter">
                <span>Book</span>
                <select wire:model.live="bookKey">
                    @foreach ($books as $book)
                        <option value="{{ $book }}">{{ $this->bookLabel($book) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="general-ledger-filter general-ledger-filter--wide">
                <span>Ledger view</span>
                <select wire:model.live="accountId">
                    <option value="">All accounts</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}{{ $account->is_active ? '' : ' (Inactive)' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="general-ledger-filter">
                <span>Cut-off date <small>(optional — defaults to cutover)</small></span>
                <input type="date" wire:model.live="cutoffDate">
            </label>
            <label class="general-ledger-filter">
                <span>To date</span>
                <input type="date" wire:model.live="toDate">
            </label>
        </div>
    </section>

    @if ($cutoverDate)
        <div class="general-ledger-meta" aria-label="Ledger coverage">
            <p class="general-ledger-coverage">
                <span class="general-ledger-meta-label">Coverage</span>
                {{ $bookLabel }} · supported transaction-level coverage begins {{ $cutoverDate }} (Manila).
                @if ($reportStartDate)
                    Reporting period: {{ $reportStartDate }} through {{ $toDate }} (inclusive).
                @endif
            </p>
            @if ($cutoverJournal)
                <p class="general-ledger-coverage">
                    <span class="general-ledger-meta-label">Opening position</span>
                    <a href="{{ $this->journalUrl($cutoverJournal) }}">{{ $cutoverJournal->reference }}</a>
                    approved opening balances dated {{ $cutoverJournal->accounting_date->format('Y-m-d') }}@if ($cutoverJournal->approver) · approved by {{ $cutoverJournal->approver->username }}@endif.
                </p>
            @endif
        </div>
    @elseif ($reportStartDate)
        <div class="general-ledger-meta" aria-label="Ledger coverage">
            <p class="general-ledger-coverage">
                <span class="general-ledger-meta-label">Coverage</span>
                {{ $bookLabel }} · posted transaction coverage starts {{ $reportStartDate }} (Manila). Approved opening balance/cutover coverage is not yet available.
            </p>
        </div>
    @endif

    @if ($dateError)
        <p class="general-ledger-empty general-ledger-empty--error" role="status">{{ $dateError }}</p>
    @elseif ($selectedAccount)
        <section class="general-ledger-card general-ledger-activity" aria-labelledby="general-ledger-activity-heading">
            <header class="general-ledger-card-heading">
                <div>
                    <p class="general-ledger-eyebrow">Account ledger</p>
                    <h2 id="general-ledger-activity-heading">
                        <span class="general-ledger-account-code">{{ $selectedAccount->code }}</span>
                        <span class="general-ledger-account-name">{{ $selectedAccount->name }}</span>
                    </h2>
                    <p class="general-ledger-caption">{{ $reportStartDate }} through {{ $toDate }} (inclusive, Manila dates)@if (! $selectedAccount->is_active) · <span class="general-ledger-badge">Inactive</span> account with posted history @endif</p>
                </div>
            </header>
            <dl class="general-ledger-summary" aria-label="Selected account ledger balances">
                <div class="general-ledger-stat">
                    <dt>Opening balance</dt>
                    <dd>{{ number_format(abs($openingBalanceCents) / 100, 2) }} {{ $openingBalanceCents < 0 ? 'Credit' : 'Debit' }}</dd>
                </div>
                <div class="general-ledger-stat">
                    <dt>Total debits</dt>
                    <dd>{{ number_format($totalDebitCents / 100, 2) }}</dd>
                </div>
                <div class="general-ledger-stat">
                    <dt>Total credits</dt>
                    <dd>{{ number_format($totalCreditCents / 100, 2) }}</dd>
                </div>
                <div class="general-ledger-stat general-ledger-stat--primary">
                    <dt>Ending balance</dt>
                    <dd>{{ number_format(abs($closingBalanceCents) / 100, 2) }} {{ $closingBalanceCents < 0 ? 'Credit' : 'Debit' }}</dd>
                </div>
            </dl>
            @if ($lines->isEmpty())
                <div class="general-ledger-empty" role="status">
                    <p>No posted activity in this period.</p>
                    <span>The opening balance remains the ending balance.</span>
                </div>
            @else
                <div class="general-ledger-table-wrap">
                    <table class="general-ledger-table general-ledger-account-table">
                        <caption class="general-ledger-sr-only">Account {{ $selectedAccount->code }} — {{ $selectedAccount->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="general-ledger-col-date">Transaction date</th>
                                <th scope="col" class="general-ledger-col-reference">Reference number</th>
                                <th scope="col" class="general-ledger-col-description">Description</th>
                                <th scope="col" class="general-ledger-numeric">Debit</th>
                                <th scope="col" class="general-ledger-numeric">Credit</th>
                                <th scope="col" class="general-ledger-numeric general-ledger-col-balance">Running balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr wire:key="general-ledger-line-{{ $line['journal']->id }}-{{ $loop->index }}">
                                    <td class="general-ledger-date">{{ $line['journal']->accounting_date->format('Y-m-d') }}</td>
                                    <td class="general-ledger-reference-cell">
                                        <a class="general-ledger-reference" href="{{ $this->journalUrl($line['journal']) }}">{{ $line['journal']->reference }}</a>
                                        <span class="general-ledger-source">{{ $line['journal']->source_type }}:{{ $line['journal']->source_id }}</span>
                                        @if ($line['journal']->correctionOf)
                                            <span class="general-ledger-source">Correction of {{ $line['journal']->correctionOf->reference }}</span>
                                        @endif
                                        @if ($sourceUrl = $this->sourceUrl($line['journal']))
                                            <a class="general-ledger-source-link" href="{{ $sourceUrl }}">Source detail</a>
                                        @endif
                                    </td>
                                    <td class="general-ledger-description-cell">
                                        <span class="general-ledger-description">{{ $line['journal']->description }}</span>
                                        @if ($line['description'])
                                            <span class="general-ledger-line-description">{{ $line['description'] }}</span>
                                        @endif
                                    </td>
                                    <td class="general-ledger-numeric">{{ $line['debit_cents'] ? number_format($line['debit_cents'] / 100, 2) : '—' }}</td>
                                    <td class="general-ledger-numeric">{{ $line['credit_cents'] ? number_format($line['credit_cents'] / 100, 2) : '—' }}</td>
                                    <td class="general-ledger-numeric general-ledger-balance">{{ number_format(abs($line['balance_cents']) / 100, 2) }} {{ $line['balance_cents'] < 0 ? 'Credit' : 'Debit' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        <section class="general-ledger-card general-ledger-activity" aria-labelledby="general-ledger-activity-heading">
            <header class="general-ledger-card-heading">
                <div>
                    <p class="general-ledger-eyebrow">Posted activity</p>
                    <h2 id="general-ledger-activity-heading">All account activity</h2>
                    <p class="general-ledger-caption">{{ $reportStartDate }} through {{ $toDate }} · inclusive Manila dates · {{ $bookLabel }} · {{ $allAccountRows->count() }} accounts with posted history</p>
                </div>
            </header>
            <dl class="general-ledger-summary" aria-label="General ledger period totals">
                <div class="general-ledger-stat">
                    <dt>Opening balance</dt>
                    <dd>{{ number_format(abs($reportOpeningCents) / 100, 2) }} {{ $reportOpeningCents < 0 ? 'Credit' : 'Debit' }}</dd>
                </div>
                <div class="general-ledger-stat">
                    <dt>Total debits</dt>
                    <dd>{{ number_format($reportDebitCents / 100, 2) }}</dd>
                </div>
                <div class="general-ledger-stat">
                    <dt>Total credits</dt>
                    <dd>{{ number_format($reportCreditCents / 100, 2) }}</dd>
                </div>
                <div class="general-ledger-stat general-ledger-stat--primary">
                    <dt>Ending balance</dt>
                    <dd>{{ number_format(abs($reportClosingCents) / 100, 2) }} {{ $reportClosingCents < 0 ? 'Credit' : 'Debit' }}</dd>
                </div>
            </dl>
            @if ($trialBalanceUrl)
                <p class="general-ledger-reconciliation">
                    <span>Ending balances use the same posted journals and inclusive date coverage as the Trial Balance.</span>
                    <a href="{{ $trialBalanceUrl }}">Reconcile with Trial Balance</a>
                </p>
            @elseif (! $dateError)
                <p class="general-ledger-reconciliation">The Trial Balance currently reports the FCDC book. Select FCDC to compare ending balances.</p>
            @endif
            @if ($allAccountRows->isEmpty())
                <div class="general-ledger-empty" role="status"><p>No posted account activity for this date range.</p><span>Adjust the cut-off or end date, or choose a different ledger view.</span></div>
            @else
                <div class="general-ledger-account-list">
                    @foreach ($allAccountRows as $accountRow)
                        <article class="general-ledger-account-group" wire:key="general-ledger-account-{{ $accountRow['account']->id }}">
                            <header class="general-ledger-account-group-heading">
                                <div class="general-ledger-account-identity">
                                    <h3>
                                        <span class="general-ledger-account-code">{{ $accountRow['account']->code }}</span>
                                        <span class="general-ledger-account-name">{{ $accountRow['account']->name }}</span>
                                        @if (! $accountRow['account']->is_active)
                                            <span class="general-ledger-badge">Inactive</span>
                                        @endif
                                    </h3>
                                    <p>{{ $accountRow['lines']->count() }} posted {{ $accountRow['lines']->count() === 1 ? 'entry' : 'entries' }} in selected period</p>
                                </div>
                                <dl class="general-ledger-account-stats">
                                    <div><dt>Opening balance</dt><dd>{{ number_format(abs($accountRow['opening']) / 100, 2) }} {{ $accountRow['opening'] < 0 ? 'Credit' : 'Debit' }}</dd></div>
                                    <div><dt>Total debits</dt><dd>{{ number_format($accountRow['total_debit_cents'] / 100, 2) }}</dd></div>
                                    <div><dt>Total credits</dt><dd>{{ number_format($accountRow['total_credit_cents'] / 100, 2) }}</dd></div>
                                    <div><dt>Ending balance</dt><dd>{{ number_format(abs($accountRow['closing']) / 100, 2) }} {{ $accountRow['closing'] < 0 ? 'Credit' : 'Debit' }}</dd></div>
                                </dl>
                            </header>
                            @if ($accountRow['lines']->isEmpty())
                                <p class="general-ledger-account-no-activity">No posted entries in this period; the opening balance carries forward.</p>
                            @else
                                <div class="general-ledger-table-wrap">
                                    <table class="general-ledger-table general-ledger-account-table">
                                        <caption class="general-ledger-sr-only">Account {{ $accountRow['account']->code }} — {{ $accountRow['account']->name }}</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col" class="general-ledger-col-date">Transaction date</th>
                                                <th scope="col" class="general-ledger-col-reference">Reference number</th>
                                                <th scope="col" class="general-ledger-col-description">Description</th>
                                                <th scope="col" class="general-ledger-numeric">Debit</th>
                                                <th scope="col" class="general-ledger-numeric">Credit</th>
                                                <th scope="col" class="general-ledger-numeric general-ledger-col-balance">Running balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($accountRow['lines'] as $line)
                                                <tr wire:key="general-ledger-all-{{ $line['journal']->id }}-{{ $loop->index }}">
                                                    <td class="general-ledger-date">{{ $line['journal']->accounting_date->format('Y-m-d') }}</td>
                                                    <td class="general-ledger-reference-cell">
                                                        <a class="general-ledger-reference" href="{{ $this->journalUrl($line['journal']) }}">{{ $line['journal']->reference }}</a>
                                                        <span class="general-ledger-source">{{ $line['journal']->source_type }}:{{ $line['journal']->source_id }}</span>
                                                        @if ($line['journal']->correctionOf)
                                                            <span class="general-ledger-source">Correction of {{ $line['journal']->correctionOf->reference }}</span>
                                                        @endif
                                                        @if ($sourceUrl = $this->sourceUrl($line['journal']))
                                                            <a class="general-ledger-source-link" href="{{ $sourceUrl }}">Source detail</a>
                                                        @endif
                                                    </td>
                                                    <td class="general-ledger-description-cell">
                                                        <span class="general-ledger-description">{{ $line['journal']->description }}</span>
                                                        @if ($line['description'])
                                                            <span class="general-ledger-line-description">{{ $line['description'] }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="general-ledger-numeric">{{ $line['debit_cents'] ? number_format($line['debit_cents'] / 100, 2) : '—' }}</td>
                                                    <td class="general-ledger-numeric">{{ $line['credit_cents'] ? number_format($line['credit_cents'] / 100, 2) : '—' }}</td>
                                                    <td class="general-ledger-numeric general-ledger-balance">{{ number_format(abs($line['balance_cents']) / 100, 2) }} {{ $line['balance_cents'] < 0 ? 'Credit' : 'Debit' }}</td>
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
