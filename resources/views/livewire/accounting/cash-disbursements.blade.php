<section class="accounting-page cash-disbursements-page" aria-labelledby="cash-disbursements-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="cash-disbursements-heading">Cash Disbursements</h1>
            <p class="dashboard-subtitle">Record direct non-inventory asset and expense payments to their actual approved Cash or Bank account.</p>
        </div>
    </header>

    @if (session()->has('disbursement-message'))<p role="status">{{ session('disbursement-message') }}</p>@endif
    @if ($errors->any())<div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="accounting-balance-grid" aria-label="Posted direct disbursements">
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Posted direct payments</p>
            <p class="accounting-unavailable">PHP {{ number_format($postedTotalCents / 100, 2) }}</p>
            <p class="dashboard-stat-note">Posted source records only; excludes linked reversal rows.</p>
        </article>
    </section>

    @can('accounting.prepare-disbursements')
        <section class="chart-account-card" aria-labelledby="disbursement-form-heading">
            <h2 id="disbursement-form-heading">{{ $editingId ? 'Edit direct payment draft' : 'Prepare direct payment' }}</h2>
            <p>Drafts have no cash, stock, payable or journal effect. Checks remain drafts until release to the payee; do not wait for bank clearance.</p>
            <form wire:submit="saveDraft">
                <label>Payee<input wire:model="payee" maxlength="180" required></label>
                <label>Payment / release date<input type="date" wire:model="paymentDate" required></label>
                <label>Method<select wire:model="method"><option>Cash</option><option>Bank Transfer</option><option>Check</option>@foreach ($otherMethods as $otherMethod)<option value="{{ $otherMethod }}">{{ $otherMethod }}</option>@endforeach</select></label>
                <label>Actual Cash / Bank account<select wire:model="moneyAccountId" required><option value="">Select approved money account</option>@foreach ($moneyAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ ucfirst($account->classification) }})</option>@endforeach</select></label>
                <label>Payment reference<input wire:model="reference" maxlength="100" required></label>
                @if ($method === 'Check')<label>Check number<input wire:model="checkNumber" maxlength="80" required></label>@endif
                <label>Description<textarea wire:model="description" maxlength="4000" required></textarea></label>
                <label>Supporting document or reference<input wire:model="evidenceReference" maxlength="255" required></label>
                <label>Payment amount (PHP)<input inputmode="decimal" wire:model="amount" required></label>
                <h3>Debit allocations</h3>
                <p>Allocate only acquired non-inventory Assets and Expenses. Allocation total must exactly equal payment amount.</p>
                @foreach ($allocations as $index => $line)
                    <fieldset wire:key="disbursement-allocation-{{ $index }}">
                        <legend>Allocation {{ $index + 1 }}</legend>
                        <label>Debit account<select wire:model="allocations.{{ $index }}.accounting_account_id" required><option value="">Select approved account</option>@foreach ($debitAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                        <label>Allocation description<input wire:model="allocations.{{ $index }}.description" maxlength="255" required></label>
                        <label>Amount (PHP)<input inputmode="decimal" wire:model="allocations.{{ $index }}.amount" required></label>
                        @if (count($allocations) > 1)<button type="button" wire:click="removeAllocation({{ $index }})">Remove allocation</button>@endif
                    </fieldset>
                @endforeach
                <button type="button" wire:click="addAllocation">Add debit allocation</button>
                <button type="submit">{{ $editingId ? 'Save draft changes' : 'Save draft' }}</button>
            </form>
        </section>
    @endcan

    <section class="dashboard-card accounting-activity" aria-labelledby="cash-disbursement-records-heading">
        <header class="dashboard-card-heading"><div><h2 id="cash-disbursement-records-heading">Direct disbursements</h2><p class="dashboard-chart-note">Search and inspect saved drafts, posted payments, and linked corrections.</p></div></header>
        <div class="payable-filters" aria-label="Filter disbursement records">
            <label><span>Search reference or payee</span><input type="search" wire:model.live.debounce.250ms="search" placeholder="Search reference, payee or evidence"></label>
            <label><span>Method</span><select wire:model.live="methodFilter"><option value="All">All methods</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Check">Check</option>@foreach ($otherMethods as $otherMethod)<option value="{{ $otherMethod }}">{{ $otherMethod }}</option>@endforeach</select></label>
            <label><span>Status</span><select wire:model.live="status"><option>All</option><option>Draft</option><option>Posted</option></select></label>
            <label><span>From date</span><input type="date" wire:model.live="fromDate"></label>
            <label><span>To date</span><input type="date" wire:model.live="toDate"></label>
        </div>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Payee</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col" class="numeric">Amount</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    @forelse ($disbursements as $disbursement)
                        <tr wire:key="cash-disbursement-{{ $disbursement->id }}">
                            <td>{{ $disbursement->reference }}</td><td>{{ $disbursement->payee }}</td><td>{{ $disbursement->payment_date->format('Y-m-d') }}</td><td>{{ $disbursement->method }}</td>
                            <td class="numeric">PHP {{ number_format($disbursement->amount_cents / 100, 2) }}</td>
                            <td>{{ $disbursement->status === 'posted' ? 'Posted' : 'Draft' }}@if ($disbursement->reversal_of_id) · Reversal of {{ $disbursement->reversalOf?->reference }}@endif</td>
                            <td>
                                <button type="button" wire:click="showDisbursement({{ $disbursement->id }})">Detail</button>
                                @if ($disbursement->status === 'draft' && ! $disbursement->reversal_of_id)
                                    @can('accounting.prepare-disbursements')<button type="button" wire:click="editDraft({{ $disbursement->id }})">Edit draft</button><button type="button" wire:click="deleteDraft({{ $disbursement->id }})">Delete draft</button>@endcan
                                    @if ($disbursement->method === 'Check')<span>Prepared check — release to payee before posting.</span>@endif
                                    @can('accounting.post-disbursements')<button type="button" wire:click="postDisbursement({{ $disbursement->id }})">{{ $disbursement->method === 'Check' ? 'Post on release' : 'Post payment' }}</button>@endcan
                                @endif
                                @if ($disbursement->status === 'posted' && ! $disbursement->reversal_of_id && $disbursement->reversals->isEmpty())
                                    @can('accounting.post-disbursements')<button type="button" wire:click="set('selectedId', {{ $disbursement->id }})">Prepare reversal</button>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="reports-empty payable-empty" colspan="7">No disbursements match this search or state.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedDisbursement)
        <section class="chart-account-card" aria-labelledby="disbursement-detail-heading">
            <h2 id="disbursement-detail-heading">Disbursement {{ $selectedDisbursement->reference }}</h2>
            <p>{{ $selectedDisbursement->description }}</p>
            <dl><dt>Method / reference</dt><dd>{{ $selectedDisbursement->method }} · {{ $selectedDisbursement->reference }}</dd><dt>Check number</dt><dd>{{ $selectedDisbursement->check_number ?: 'Not applicable' }}</dd><dt>Money account</dt><dd>{{ $selectedDisbursement->moneyAccount->code }} — {{ $selectedDisbursement->moneyAccount->name }}</dd><dt>Evidence</dt><dd>{{ $selectedDisbursement->evidence_reference }}</dd><dt>Amount</dt><dd>PHP {{ number_format($selectedDisbursement->amount_cents / 100, 2) }}</dd><dt>Journal</dt><dd>{{ $selectedDisbursement->journal?->reference ?? 'Not posted' }}</dd></dl>
            <h3>Debit allocations</h3><ul>@foreach ($selectedDisbursement->lines as $line)<li>{{ $line->account->code }} — {{ $line->account->name }}: {{ $line->description }} · PHP {{ number_format($line->amount_cents / 100, 2) }}</li>@endforeach</ul>
            @if ($selectedDisbursement->reversal_of_id)<p>Linked reversal of {{ $selectedDisbursement->reversalOf?->reference }}. Reason: {{ $selectedDisbursement->correction_reason }}</p>@endif
            @if ($selectedDisbursement->reversals->isNotEmpty())<p>Reversed by {{ $selectedDisbursement->reversals->first()->reference }}. Reason: {{ $selectedDisbursement->reversals->first()->correction_reason }}</p>@endif
            @if ($selectedDisbursement->status === 'posted' && ! $selectedDisbursement->reversal_of_id && $selectedDisbursement->reversals->isEmpty())
                @can('accounting.post-disbursements')
                    <form wire:submit="reverseDisbursement({{ $selectedDisbursement->id }})"><label>Reason for returned / voided payment<textarea wire:model="reversalReason" maxlength="4000" required></textarea></label><button type="submit">Post linked reversal</button></form>
                @endcan
            @endif
        </section>
    @endif
</section>
