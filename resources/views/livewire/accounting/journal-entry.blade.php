<section class="journal-entry-page" aria-labelledby="journal-entry-heading">
    <header class="page-heading journal-entry-heading">
        <div>
            <h1 id="journal-entry-heading">Journal Entry</h1>
            <p class="page-subtitle">Prepare reviewed manual drafts, generate journals from POS and recorded cost sources, and correct posted history through linked reversals.</p>
        </div>
    </header>

    <div
        class="journal-entry-feedback"
        role="status"
        aria-live="polite"
        x-data="{ visible: @js(session()->has('journal-entry-message')), message: @js(session('journal-entry-message', '')) }"
        x-init="if (visible) setTimeout(() => visible = false, 5000)"
        x-show="visible"
        x-transition.opacity
    >
        <span x-text="message"></span>
        <button type="button" aria-label="Dismiss notification" @click="visible = false">&times;</button>
    </div>

    <nav class="journal-section-nav" aria-label="Journal entry sections">
        <a href="#regular-journal-entries">Regular Journal Entries</a>
        <a href="#source-generated-journals">Source-Generated Journals</a>
    </nav>

    <section class="journal-workflow-section" id="regular-journal-entries" aria-labelledby="journal-regular-heading">
        <header class="journal-workflow-header">
            <span class="journal-manual-kicker">Regular entries</span>
            <h2 id="journal-regular-heading">Regular Journal Entries</h2>
            <p>Prepare reviewed manual drafts and browse the journal register. Draft and pending-review entries do not affect posted balances.</p>
        </header>

        <section class="journal-manual-panel {{ $accounts->isEmpty() ? 'is-empty' : '' }}" aria-labelledby="journal-manual-heading">
            <div>
                <span class="journal-manual-kicker">Manual journal</span>
                <h2 id="journal-manual-heading">{{ $accounts->isEmpty() ? 'Manual journal drafts are unavailable' : (auth()->user()->cannot('accounting.create-journal-entry') ? 'Manual journal drafts (read-only)' : 'New Manual Journal Draft') }}</h2>
                <p>
                    @if ($accounts->isEmpty())
                        No approved active accounts are available. Ask an administrator to approve accounts before creating a journal entry.
                    @elseif (auth()->user()->cannot('accounting.create-journal-entry'))
                        View-only access. You need the prepare-manual-journal-entries permission to create a draft.
                    @else
                        Start a manual journal draft, add balanced account lines, then submit it for approval.
                    @endif
                </p>
            </div>
            @can('accounting.create-journal-entry')
                @unless ($accounts->isEmpty())
                    <button class="journal-save-button" type="button" wire:click="openNewDraft">New Manual Journal Draft</button>
                @endunless
            @endcan
        </section>
        @can('accounting.create-journal-entry')
            @unless ($accounts->isEmpty())
            @if ($showEntryModal)
            <div class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" wire:click.self="closeEntryModal" wire:keydown.escape.window="closeEntryModal">
            <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none" role="dialog" aria-modal="true" aria-labelledby="journal-entry-modal-heading">
            <form class="journal-entry-card !m-0 !border-0 !shadow-none !p-[22px] max-sm:!p-[18px_14px]" wire:submit="{{ $isCorrecting ? 'correct('.$correctingJournalId.')' : 'saveDraft' }}">
                <header class="mb-[18px] flex items-start justify-between gap-4">
                    <div><h2 id="journal-entry-modal-heading" class="mb-1 text-base">{{ $isCorrecting ? 'Correct posted journal' : ($editingDraftId ? 'Edit journal draft' : 'New manual journal draft') }}</h2>
                        <p class="m-0 text-xs text-slate-500">Enter the transaction details and make sure debits equal credits before saving.</p></div>
                    <button class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700" type="button" wire:click="closeEntryModal" aria-label="Close journal entry">&times;</button>
                </header>
                <div class="journal-entry-context" aria-label="Entry source and status">
                    <span><strong>Source module</strong> {{ $isCorrecting ? 'Manual correction' : 'Manual input' }}</span>
                    <span><strong>Status</strong> {{ $isCorrecting ? 'Posts immediately' : ($editingDraftId ? 'Draft' : 'New draft') }}</span>
                    <span><strong>Workflow</strong> Draft → reviewed → posted</span>
                </div>
                <div class="journal-section-heading"><h3>Entry details</h3><p>Accounting date, reference, description, and entry type.</p></div>
                <div class="journal-entry-header-fields">
                    <label><span>Accounting date</span><input type="date" wire:model="accountingDate">@error('accountingDate') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    <label><span>Journal reference</span><input type="text" wire:model="reference" maxlength="80">@error('reference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    <label><span>External reference</span><input type="text" wire:model="externalReference" maxlength="120">@error('externalReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    <label><span>Entry type</span>
                        <select wire:model="entryType" @disabled($isCorrecting)>
                            <option value="standard">Standard entry</option>
                            <option value="adjusting">Adjusting entry</option>
                        </select>
                        @error('entryType') <small class="settings-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="journal-entry-description"><span>Description</span><input type="text" wire:model="description" maxlength="255">@error('description') <small class="settings-error">{{ $message }}</small> @enderror</label>
                </div>

                @if ($isCorrecting)
                    <div class="journal-entry-header-fields">
                        <label><span>Correction reason</span><input type="text" wire:model="correctionReason" maxlength="1000">@error('correctionReason') <small class="settings-error">{{ $message }}</small> @enderror</label>
                        <label><span>Reversal reference</span><input type="text" wire:model="correctionReference" maxlength="80">@error('correctionReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                        <label><span>Replacement reference</span><input type="text" wire:model="replacementReference" maxlength="80">@error('replacementReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    </div>
                @endif

                <div class="journal-lines-heading">
                    <div><h3>Account lines</h3><p>Use approved active accounts. Accounts Payable and Inventory require an external reference and the reviewed manual-input workflow.</p></div>
                    <button class="journal-secondary-button" type="button" wire:click="addLine" @disabled(count($lines) >= 100)>Add line</button>
                </div>
                <div class="journal-line-list" role="region" aria-label="Journal entry lines" tabindex="0">
                    <table class="journal-lines-table">
                        <thead><tr><th scope="col">#</th><th scope="col">Account</th><th scope="col">Line description</th><th scope="col">Debit (PHP)</th><th scope="col">Credit (PHP)</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody>
                    @foreach ($lines as $index => $line)
                        <tr wire:key="journal-line-{{ $index }}">
                            <th scope="row" class="journal-line-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</th>
                            <td><label class="sr-only" for="journal-account-{{ $index }}">Account, line {{ $index + 1 }}</label>
                                <select id="journal-account-{{ $index }}" wire:model="lines.{{ $index }}.accountId"><option value="">Select account</option>
                                    @foreach ($accountsByType as $accountType => $typeAccounts)
                                        <optgroup label="{{ $accountType }}">
                                            @foreach ($typeAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }} · {{ $accountType }}</option>@endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </td>
                            <td><label class="sr-only" for="journal-description-{{ $index }}">Line description, line {{ $index + 1 }}</label><input id="journal-description-{{ $index }}" type="text" wire:model="lines.{{ $index }}.description" maxlength="255" placeholder="Optional detail"></td>
                            <td><label class="sr-only" for="journal-debit-{{ $index }}">Debit amount, line {{ $index + 1 }}</label><input id="journal-debit-{{ $index }}" type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.debit" placeholder="0.00"></td>
                            <td><label class="sr-only" for="journal-credit-{{ $index }}">Credit amount, line {{ $index + 1 }}</label><input id="journal-credit-{{ $index }}" type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.credit" placeholder="0.00"></td>
                            <td class="journal-line-action">
                                <button class="journal-remove-button" type="button" wire:click="removeLine({{ $index }})" aria-label="Remove line {{ $index + 1 }}" @disabled(count($lines) <= 2)>Remove</button>
                            </td>
                        </tr>
                        @error("lines.{$index}")
                            <tr class="journal-line-error-row" wire:key="journal-line-{{ $index }}-error">
                                <td colspan="6"><p class="journal-line-error" role="alert">{{ $message }}</p></td>
                            </tr>
                        @enderror
                    @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="journal-section-heading"><h3>Debit and credit totals</h3><p>The entry can only be submitted when both totals are equal and positive.</p></div>
                <div class="journal-entry-totals {{ $isBalanced ? 'is-balanced' : 'is-unbalanced' }}" aria-live="polite" aria-atomic="true">
                    <div><span>Debit total</span><strong>₱{{ number_format($debitTotal, 2) }}</strong></div>
                    <div><span>Credit total</span><strong>₱{{ number_format($creditTotal, 2) }}</strong></div>
                    <div class="journal-balance-state"><strong>{{ $isBalanced ? 'Balanced' : 'Not balanced' }}</strong>@unless ($isBalanced)<span>Debits and credits must match before this entry can be submitted.</span>@endunless</div>
                </div>
                @error('lines') <p class="settings-error journal-total-error" role="alert">{{ $message }}</p> @enderror
                @error('journal') <p class="settings-error journal-total-error" role="alert">{{ $message }}</p> @enderror
                @if ($isCorrecting)
                    <button class="journal-save-button" type="submit" @disabled(auth()->user()->cannot('accounting.post-journal-entry'))>Post linked reversal and replacement</button>
                @else
                    <button class="journal-save-button" type="submit">Save draft</button>
                @endif
            </form>
            </section>
            </div>
            @endif
            @endunless
        @endcan

        <section class="journal-history-card" aria-labelledby="journal-history-heading">
            <header><div><h2 id="journal-history-heading">Recent journal entries</h2><p>Source-generated and manual entries. Draft and pending-review entries do not affect posted balances.</p></div></header>
            <div class="journal-register-toolbar">
                <label class="journal-register-search"><span>Search journal entries</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Reference or description"></label>
                <details class="journal-entry-filters">
                    <summary>More filters</summary>
                    <div class="journal-entry-header-fields">
                        <label><span>From</span><input type="date" wire:model.live="dateFrom"></label>
                        <label><span>To</span><input type="date" wire:model.live="dateTo"></label>
                        <label><span>Source module</span><select wire:model.live="sourceFilter"><option value="">All sources</option>@foreach ($sourceLabels as $sourceValue => $sourceLabel)<option value="{{ $sourceValue }}">{{ $sourceLabel }}</option>@endforeach</select></label>
                        <label><span>Status</span><select wire:model.live="statusFilter"><option value="">All statuses</option><option value="draft">Draft</option><option value="pending_review">Pending review</option><option value="approved">Approved</option><option value="posted">Posted</option></select></label>
                        <label><span>Account type</span><select wire:model.live="accountTypeFilter"><option value="">All account types</option><option value="Asset">Asset</option><option value="Liability">Liability</option><option value="Equity">Equity</option><option value="Revenue">Revenue</option><option value="contra_revenue">Contra-Revenue</option><option value="cost_of_sales">Cost of Sales</option><option value="operating_expense">Expense</option></select></label>
                        <label><span>Entry type</span><select wire:model.live="entryTypeFilter"><option value="">All entries</option><option value="standard">Standard</option><option value="adjusting">Adjusting</option></select></label>
                    </div>
                </details>
            </div>
            @if ($journals->isNotEmpty())
            <div class="journal-register-scroll"><table class="journal-register-table"><thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Description / lines</th><th scope="col">Status</th><th scope="col" class="journal-register-amount">Debit</th><th scope="col" class="journal-register-amount">Credit</th><th scope="col">Actions</th></tr></thead><tbody>
            @foreach ($journals as $journal)
                <tr wire:key="journal-{{ $journal->id }}">
                    <td class="journal-register-date">{{ $journal->accounting_date->format('Y-m-d') }}<span>{{ $this->sourceLabel($journal->source_type) }}@if ($journal->entry_type === 'adjusting') · Adjusting @endif</span></td>
                    <th scope="row" class="journal-register-reference">{{ $journal->reference }}
                        @if ($journal->external_reference)<span>Ext: {{ $journal->external_reference }}</span>@endif
                        @if ($journal->correction_of_id)<span>Corrects {{ $journal->correctionOf?->reference }}</span>@endif
                    </th>
                    <td class="journal-register-description"><strong>{{ $journal->description }}</strong>@if ($journal->entry_type === 'adjusting') <span class="journal-entry-adjusted-badge">Adjusting entry</span>@endif
                        @if ($journal->manual_input) <span class="journal-entry-manual-badge">Manual input</span>@endif
                        <details class="journal-register-lines"><summary>{{ $journal->lines->count() }} account {{ $journal->lines->count() === 1 ? 'line' : 'lines' }} · View lines</summary>
                            <div class="journal-history-lines">
                                @foreach ($journal->lines as $line)
                                    <div><span>{{ $line->account->code }} · {{ $line->account->name }}@if ($line->description) — {{ $line->description }}@endif</span><span>Dr {{ number_format($line->debit_cents / 100, 2) }} · Cr {{ number_format($line->credit_cents / 100, 2) }}</span></div>
                                @endforeach
                            </div>
                        </details>
                    </td>
                    <td>
                        <span class="journal-register-status status-{{ $journal->status }}">{{ ucwords(str_replace('_', ' ', $journal->status)) }}</span>
                        <small class="muted" style="display: block; margin-top: 0.25rem;">
                            {{ $journal->source_type === 'opening'
                                ? 'Opening balance / cutover'
                                : (in_array($journal->source_type, ['manual', 'reversal', 'replacement'], true)
                                    ? 'Manual journal workflow'
                                    : 'Automatic source posting') }}
                        </small>
                    </td>
                    <td class="journal-register-amount">₱{{ number_format($journal->lines->sum('debit_cents') / 100, 2) }}</td>
                    <td class="journal-register-amount">₱{{ number_format($journal->lines->sum('credit_cents') / 100, 2) }}</td>
                    <td><div class="journal-register-actions">
                        <button type="button" wire:click="showJournal({{ $journal->id }})">Detail</button>
                        @if ($journal->status === 'draft' && $journal->source_type === 'manual')
                            @can('accounting.create-journal-entry')
                                <button type="button" wire:click="editDraft({{ $journal->id }})">Edit</button>
                                <button type="button" data-delete-journal="{{ $journal->id }}" data-journal-reference="{{ $journal->reference }}">Delete</button>
                                <button type="button" wire:click="submitForReview({{ $journal->id }})">Submit</button>
                            @endcan
                        @elseif ($journal->status === 'pending_review' && $journal->source_type === 'manual')
                            @can('accounting.approve-journal-entry')
                                <button type="button" wire:click="approveDraft({{ $journal->id }})">Approve</button>
                                <button type="button" wire:click="returnForChanges({{ $journal->id }})">Return</button>
                            @endcan
                        @elseif ($journal->status === 'approved' && $journal->source_type === 'manual')
                            @can('accounting.post-journal-entry')<button type="button" wire:click="postDraft({{ $journal->id }})">Post</button>@endcan
                        @elseif ($journal->status === 'posted' && in_array($journal->source_type, ['manual', 'replacement'], true) && $journal->corrections->isEmpty())
                            @can('accounting.create-journal-entry')<button type="button" wire:click="beginCorrection({{ $journal->id }})">Correct</button>@endcan
                        @endif
                    </div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            @else
                <p class="journal-history-empty">No journal entries match these filters. Saved drafts and posted entries appear here.</p>
            @endif
        </section>
    </section>

    @php
        $posSaleJournals = $journals->where('source_type', 'pos_sale');
        $posJournals = $posSaleJournals->take(5);
    @endphp
    <section class="journal-source-panel" id="source-generated-journals" aria-labelledby="journal-source-heading">
        <header>
            <div>
                <h2 id="journal-source-heading">Source-Generated Journals</h2>
                <p>POS sales post automatically at checkout. Validated operating cost records post automatically when their approved accounts are configured. Any remaining reworking and operating cost records can be posted here from their saved source records.</p>
            </div>
            <span class="journal-source-note">One journal per source record</span>
        </header>

        @unless (auth()->user()->can('accounting.post-journal-entry'))
            <p class="journal-section-notice" role="status">View-only access. You need post-journal-entry permission to generate a source journal.</p>
        @endunless

        <div class="journal-source-sections">
            <section class="journal-source-section" aria-labelledby="journal-source-pos-sale-heading">
                <header>
                    <div>
                        <h3 id="journal-source-pos-sale-heading">POS sales</h3>
                        <p>The five most recent matching POS sale journals. Completed sales post automatically at checkout.</p>
                    </div>
                    <span class="journal-source-count">{{ $posSaleJournals->count() }} {{ $posSaleJournals->count() === 1 ? 'record' : 'records' }}</span>
                </header>
                @if ($posJournals->isEmpty())
                    <p class="journal-source-empty">No POS sale journals match the current filters. Completed sales post automatically at checkout.</p>
                @else
                    <div class="journal-source-table-scroll">
                        <table class="journal-source-table">
                            <thead>
                                <tr>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" class="journal-source-amount">Amount (PHP)</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="journal-source-action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($posJournals as $posJournal)
                                    <tr wire:key="source-pos-sale-{{ $posJournal->id }}">
                                        <th scope="row" class="journal-source-reference">
                                            {{ $posJournal->reference }}
                                            @if ($posJournal->external_reference)<span>Ext: {{ $posJournal->external_reference }}</span>@endif
                                        </th>
                                        <td class="journal-source-date">{{ $posJournal->accounting_date->format('Y-m-d') }}</td>
                                        <td class="journal-source-description">{{ $posJournal->description }}</td>
                                        <td class="journal-source-amount">₱{{ number_format($posJournal->lines->sum('debit_cents') / 100, 2) }}</td>
                                        <td><span class="journal-source-status is-posted">{{ ucwords(str_replace('_', ' ', $posJournal->status)) }}</span></td>
                                        <td class="journal-source-action"><button type="button" wire:click="showJournal({{ $posJournal->id }})">View</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @foreach ($sourceCandidates as $sourceType => $group)
                <section class="journal-source-section" aria-labelledby="journal-source-{{ $sourceType }}-heading">
                    <header>
                        <div>
                            <h3 id="journal-source-{{ $sourceType }}-heading">{{ $group['label'] }}</h3>
                            <p>{{ $sourceType === 'reworking_cost' ? 'Recorded reworking costs that do not yet have a journal.' : 'Recorded operating expenses that do not yet have a journal. New validated records post automatically when accounting is configured.' }}</p>
                        </div>
                        <span class="journal-source-count">{{ count($group['records']) }} {{ count($group['records']) === 1 ? 'record' : 'records' }}</span>
                    </header>
                    @if ($group['records'] === [])
                        <p class="journal-source-empty">No unposted source records.</p>
                    @else
                        <div class="journal-source-table-scroll">
                            <table class="journal-source-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Reference</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Description</th>
                                        <th scope="col" class="journal-source-amount">Amount (PHP)</th>
                                        <th scope="col">Status</th>
                                        <th scope="col" class="journal-source-action">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['records'] as $candidate)
                                        <tr wire:key="source-{{ $sourceType }}-{{ $candidate['id'] }}">
                                            <th scope="row" class="journal-source-reference">{{ $candidate['reference'] }}</th>
                                            <td class="journal-source-date">{{ $candidate['date'] ?? '—' }}</td>
                                            <td class="journal-source-description">{{ $candidate['description'] ?: '—' }}</td>
                                            <td class="journal-source-amount">₱{{ number_format($candidate['amount_cents'] / 100, 2) }}</td>
                                            <td>
                                                <span class="journal-source-status {{ $candidate['eligible'] ? 'is-ready' : 'is-blocked' }}">
                                                    {{ $candidate['eligible'] ? 'Ready to post' : 'Needs attention' }}
                                                </span>
                                            </td>
                                            <td class="journal-source-action">
                                                @if ($candidate['eligible'] && auth()->user()->can('accounting.post-journal-entry'))
                                                    <button type="button" wire:click="generateSourceJournal('{{ $sourceType }}', {{ $candidate['id'] }})">Generate journal</button>
                                                @elseif ($candidate['eligible'])
                                                    <span class="journal-source-action-note">View-only</span>
                                                @else
                                                    <span class="journal-source-action-note">No action available</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @unless ($candidate['eligible'])
                                            <tr class="journal-source-error-row" wire:key="source-{{ $sourceType }}-{{ $candidate['id'] }}-error">
                                                <td colspan="6">
                                                    <p class="journal-source-error" role="alert"><strong>Cannot generate journal:</strong> {{ $candidate['reason'] }}</p>
                                                </td>
                                            </tr>
                                        @endunless
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($errors->has('payment_method') || $errors->has('journal'))
            <div class="journal-source-alert" role="alert">
                <strong>Journal action could not be completed</strong>
                <ul>
                    @foreach (['payment_method', 'journal'] as $errorField)
                        @foreach ($errors->get($errorField) as $errorMessage)
                            <li>{{ $errorMessage }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @if ($selectedJournal)
        <div class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" wire:click.self="$set('selectedJournalId', null)" wire:keydown.escape.window="$set('selectedJournalId', null)">
            <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none max-sm:p-4" role="dialog" aria-modal="true" aria-labelledby="journal-detail-heading">
                <header class="mb-5 flex items-start justify-between gap-4">
                    <div class="min-w-0"><h2 id="journal-detail-heading" class="m-0 break-words text-base font-semibold text-slate-800">{{ $selectedJournal->reference }}</h2>
                        <p class="mb-0 mt-1 text-xs text-slate-500">{{ $selectedJournal->accounting_date->format('F j, Y') }} · {{ $this->sourceLabel($selectedJournal->source_type) }}{{ $selectedJournal->entry_type === 'adjusting' ? ' · Adjusting entry' : '' }}{{ $selectedJournal->manual_input ? ' · Manual input' : '' }}</p></div>
                    <button class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700" type="button" wire:click="$set('selectedJournalId', null)" aria-label="Close journal details">&times;</button>
                </header>
                <p class="mb-5 text-sm text-slate-700">{{ $selectedJournal->description }}</p>
                <dl class="mb-5 grid grid-cols-2 gap-x-6 gap-y-4 rounded-lg bg-slate-50 p-4 text-xs max-sm:grid-cols-1">
                    <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-medium text-slate-800">{{ ucwords(str_replace('_', ' ', $selectedJournal->status)) }}</dd></div>
                    <div><dt class="text-slate-500">Source module</dt><dd class="mt-1 font-medium text-slate-800">{{ $this->sourceLabel($selectedJournal->source_type) }}</dd></div>
                    <div><dt class="text-slate-500">Input mode</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->manual_input ? 'Manual input' : 'Source generated' }}</dd></div>
                    <div><dt class="text-slate-500">External reference</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->external_reference ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Prepared by</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->preparer?->username ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Approved by</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->approver?->username ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Posted by</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->poster?->username ?? '—' }}</dd></div>
                    @if ($selectedJournal->correction_of_id)<div><dt class="text-slate-500">Corrects</dt><dd class="mt-1 font-medium text-slate-800">{{ $selectedJournal->correctionOf?->reference ?? '—' }}</dd></div>@endif
                </dl>
                @if ($selectedJournal->correction_reason)<p class="mb-5 text-xs text-slate-700"><strong>Correction reason:</strong> {{ $selectedJournal->correction_reason }}</p>@endif
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Account lines</h3>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full min-w-[560px] border-collapse text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500"><tr><th class="border-b border-slate-200 px-3 py-2 font-semibold">Account</th><th class="border-b border-slate-200 px-3 py-2 font-semibold">Description</th><th class="border-b border-slate-200 px-3 py-2 text-right font-semibold">Debit</th><th class="border-b border-slate-200 px-3 py-2 text-right font-semibold">Credit</th></tr></thead>
                        <tbody>
                            @foreach ($selectedJournal->lines as $line)
                                <tr wire:key="journal-detail-line-{{ $line->id }}"><td class="border-b border-slate-100 px-3 py-2 text-slate-700">{{ $line->account->code }} · {{ $line->account->name }}</td><td class="border-b border-slate-100 px-3 py-2 text-slate-500">{{ $line->description ?: '—' }}</td><td class="border-b border-slate-100 px-3 py-2 text-right tabular-nums text-slate-700">₱{{ number_format($line->debit_cents / 100, 2) }}</td><td class="border-b border-slate-100 px-3 py-2 text-right tabular-nums text-slate-700">₱{{ number_format($line->credit_cents / 100, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 font-semibold text-slate-800"><tr><td class="px-3 py-2" colspan="2">Total</td><td class="px-3 py-2 text-right tabular-nums">₱{{ number_format($selectedJournal->lines->sum('debit_cents') / 100, 2) }}</td><td class="px-3 py-2 text-right tabular-nums">₱{{ number_format($selectedJournal->lines->sum('credit_cents') / 100, 2) }}</td></tr></tfoot>
                    </table>
                </div>
                <footer class="mt-5 flex justify-end"><button class="journal-secondary-button" type="button" wire:click="$set('selectedJournalId', null)">Close details</button></footer>
            </section>
        </div>
    @endif
</section>
