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
                <dt>{{ ucfirst($name) }}</dt><dd>{{ $state }}</dd>
            @endforeach
        </dl>
        <p>Opening Accounts Payable requires an approved supplier/invoice schedule. Opening Inventory requires approved per-item quantities and values; neither control account can be approved as an unrestricted general-ledger balance.</p>
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
</section>
