<section class="opening-books-page" aria-labelledby="opening-books-heading">
    <header class="page-heading">
        <div>
            <h1 id="opening-books-heading">Opening Books &amp; Cutover</h1>
            <p class="page-subtitle">Approve the Manila cutover date, opening balances and current-year historical summaries.</p>
        </div>
    </header>

    @if (session()->has('opening-message'))
        <p role="status">{{ session('opening-message') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="chart-account-card" aria-labelledby="cutover-readiness-heading">
        <h2 id="cutover-readiness-heading">Readiness and historical coverage</h2>
        <dl>
            @foreach ($readiness as $name => $state)
                @if (is_string($state))
                    <dt>{{ ucfirst($name) }}</dt><dd>{{ $state }}</dd>
                @endif
            @endforeach
        </dl>
        <p>Opening Accounts Payable requires an opening invoice schedule whose exact total matches the controlled AP credit line. Opening Inventory requires approved per-item quantities and values; neither control account can be approved as an unrestricted general-ledger balance.</p>
    </section>

    <section class="chart-account-card" aria-labelledby="production-activation-heading">
        <h2 id="production-activation-heading">Production accounting activation</h2>
        <p role="status">{{ $readiness['production'] }}</p>
        @if (! $readiness['production_activated'])
            @can('accounting.activate-books')
                @if ($readiness['production_ready'])
                    <button type="button" wire:click="activateProduction">Activate production accounting</button>
                @else
                    <button type="button" disabled>Resolve readiness blockers before activation</button>
                @endif
            @endcan
        @endif
    </section>

    <section class="chart-account-card" aria-labelledby="opening-journal-heading">
        <h2 id="opening-journal-heading">Opening journal</h2>
        @if ($journal?->status === 'posted')
            <p>Approved cutover: {{ \Carbon\CarbonImmutable::parse($journal->accounting_date, 'Asia/Manila')->format('F j, Y') }}</p>
            <p>Journal {{ $journal->reference }} · approved by {{ $journal->approver?->username ?? 'Unavailable' }} · {{ $journal->approved_at?->timezone('UTC')->format('Y-m-d H:i:s') }} UTC</p>
            <table><thead><tr><th>Account</th><th>Debit (PHP)</th><th>Credit (PHP)</th></tr></thead><tbody>
                @foreach ($journal->lines as $line)
                    <tr><td>{{ $line->account->code }} — {{ $line->account->name }}</td><td>{{ number_format($line->debit_cents / 100, 2) }}</td><td>{{ number_format($line->credit_cents / 100, 2) }}</td></tr>
                @endforeach
            </tbody></table>
        @else
            <form wire:submit="saveOpening">
                <label>Cutover date (Asia/Manila)<input type="date" wire:model="cutoverDate" required></label>
                <p>Fiscal year is January 1 through December 31. Use exact PHP centavos; debit and credit totals must match.</p>
                @foreach ($lines as $index => $line)
                    <div wire:key="opening-line-{{ $index }}">
                        <label>Approved account
                            <select wire:model="lines.{{ $index }}.accountId" required>
                                <option value="">Select account</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->classification }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Debit PHP<input inputmode="decimal" wire:model="lines.{{ $index }}.debit"></label>
                        <label>Credit PHP<input inputmode="decimal" wire:model="lines.{{ $index }}.credit"></label>
                    </div>
                @endforeach
                @can('accounting.maintain-opening-books')
                    <button type="button" wire:click="addOpeningLine">Add line</button>
                    <button type="submit">Save opening journal</button>
                @endcan
            </form>
            @if ($journal?->status === 'draft')
                <p>Pending approval. Prepared by {{ $journal->preparer?->username ?? 'staff member' }}.</p>
                @can('accounting.approve-opening-books')<button type="button" wire:click="approveOpening">Approve opening journal</button>@endcan
            @endif
        @endif
    </section>

    <section class="chart-account-card" aria-labelledby="opening-inventory-heading">
        <h2 id="opening-inventory-heading">Opening Inventory valuation</h2>
        <p>Record accountant-approved quantities and carrying values against each item's stock timeline at cutover. This schedule establishes accounting cost only; it does not add a physical receipt or use editable item unit cost as historical evidence.</p>
        @if ($inventoryValuation?->status === 'approved' && ! $editingInventoryValuation)
            <p>Approved for {{ $inventoryValuation->cutover_date->format('F j, Y') }} · evidence: {{ $inventoryValuation->evidence_reference }} · approved by {{ $inventoryValuation->approver?->username ?? 'Unavailable' }}</p>
            <table><thead><tr><th>Inventory item</th><th>Approved quantity</th><th>Opening unit cost (PHP)</th><th>Remaining value (PHP)</th></tr></thead><tbody>
                @foreach ($inventoryValuation->lines as $line)
                    <tr>
                        <td>{{ $line->inventory->code }} — {{ $line->inventory->name }} ({{ $line->inventory->unit }})</td>
                        <td>{{ $line->quantity }}</td>
                        <td>{{ $line->quantity > 0 ? number_format(($line->carrying_value_cents / 100) / (float) $line->quantity, 4) : '0.0000' }}</td>
                        <td>{{ number_format($line->carrying_value_cents / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody></table>
            @if ($journal?->status !== 'posted')
                @can('accounting.maintain-opening-books')<button type="button" wire:click="reviseInventoryValuation">Revise opening inventory schedule</button>@endcan
            @endif
        @else
            <form wire:submit="saveInventoryValuation">
                <label>Cutover count/value evidence<input wire:model="inventoryEvidence" maxlength="255" required></label>
                @foreach ($inventoryLines as $index => $line)
                    @php($item = $inventoryItems->firstWhere('id', (int) $line['inventoryId']))
                    <div wire:key="opening-inventory-line-{{ $index }}">
                        <strong>{{ $item?->code }} — {{ $item?->name }} ({{ $item?->unit }})</strong>
                        <label>Approved opening quantity<input inputmode="decimal" wire:model="inventoryLines.{{ $index }}.quantity" required></label>
                        <label>Approved carrying value (PHP)<input inputmode="decimal" wire:model="inventoryLines.{{ $index }}.value" required></label>
                    </div>
                @endforeach
                @can('accounting.maintain-opening-books')<button type="submit">Save opening inventory schedule</button>@endcan
            </form>
            @if ($inventoryValuation?->status === 'draft')
                <p>Opening inventory valuation pending approval. Prepared by {{ $inventoryValuation->preparer?->username ?? 'staff member' }}.</p>
                @can('accounting.approve-opening-books')<button type="button" wire:click="approveInventoryValuation">Approve opening valuation</button>@endcan
            @endif
        @endif
    </section>

    <section class="chart-account-card" aria-labelledby="ytd-summary-heading">
        <h2 id="ytd-summary-heading">Pre-cutover year-to-date income and expense summary</h2>
        <p>Separate from balance-sheet opening balances and post-cutover operational activity. Required to claim current-year coverage for a midyear cutover; retain the supporting evidence reference.</p>
        @if ($ytdSummary?->status === 'approved')
            <p>Approved through {{ $ytdSummary->through_date->format('F j, Y') }} · evidence: {{ $ytdSummary->evidence_reference }} · approved by {{ $ytdSummary->approver?->username ?? 'Unavailable' }}</p>
            <table><thead><tr><th>Account</th><th>YTD amount (PHP)</th></tr></thead><tbody>
                @foreach ($ytdSummary->lines as $line)
                    <tr><td>{{ $line->account->code }} — {{ $line->account->name }} ({{ $line->account->type }})</td><td>{{ number_format($line->amount_cents / 100, 2) }}</td></tr>
                @endforeach
            </tbody></table>
        @else
            <form wire:submit="saveYtdSummary">
                <label>Summary through (day before cutover)<input type="date" wire:model="ytdThroughDate"></label>
                <label>Supporting schedule/evidence reference<input wire:model="ytdEvidence" maxlength="255"></label>
                @foreach ($ytdLines as $index => $line)
                    <div wire:key="ytd-line-{{ $index }}">
                        <label>Approved Revenue or Expense account
                            <select wire:model="ytdLines.{{ $index }}.accountId">
                                <option value="">Select account</option>
                                @foreach ($accounts->whereIn('type', ['Revenue', 'Expense']) as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ $account->type }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label>YTD amount PHP<input inputmode="decimal" wire:model="ytdLines.{{ $index }}.amount"></label>
                    </div>
                @endforeach
                @can('accounting.maintain-opening-books')
                    <button type="button" wire:click="addYtdLine">Add YTD line</button>
                    <button type="submit">Save YTD summary</button>
                @endcan
            </form>
            @if ($ytdSummary?->status === 'draft')
                <p>YTD summary pending approval.</p>
                @can('accounting.approve-opening-books')<button type="button" wire:click="approveYtdSummary">Approve YTD summary</button>@endcan
            @endif
        @endif
    </section>

    <section class="chart-account-card" aria-labelledby="intervening-sources-heading">
        <h2 id="intervening-sources-heading">Post-cutover source reconciliation</h2>
        <p>Existing completed activity from the approved cutover is listed once. Including a source creates only its accounting link; it does not recreate a sale or stock movement. Unsupported historical costs remain blockers.</p>
        @if ($interveningSources->isEmpty())
            <p>No post-cutover source activity found.</p>
        @else
            <table>
                <thead><tr><th>Date</th><th>Source</th><th>Reference</th><th>State</th><th>Detail</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach ($interveningSources as $source)
                        <tr wire:key="intervening-source-{{ $source['type'] }}-{{ $source['id'] }}">
                            <td>{{ $source['date'] }}</td>
                            <td>{{ $source['description'] }}</td>
                            <td>{{ $source['reference'] }}</td>
                            <td>{{ $source['status'] }}</td>
                            <td>{{ $source['reason'] ?? ($source['journal_id'] ? 'Journal '.$source['journal_id'] : 'Approved evidence is available.') }}</td>
                            <td>
                                @if ($source['status'] === 'missing' && in_array($source['type'], ['pos_sale', 'stock_movement', 'supplier_purchase', 'cash_disbursement'], true))
                                    @can('accounting.reconcile-sources')
                                        @can('accounting.post-reconciled-sources')
                                            <button type="button" wire:click="includeInterveningSource('{{ $source['type'] }}', {{ $source['id'] }})">Include source</button>
                                        @endcan
                                    @endcan
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</section>
