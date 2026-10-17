<section class="journal-entry-page" aria-labelledby="journal-entry-heading">
    <header class="page-heading journal-entry-heading">
        <div>
            <h1 id="journal-entry-heading">Journal Entry</h1>
            <p class="page-subtitle">Prepare persistent manual drafts, post approved entries, and correct posted history through linked reversals.</p>
        </div>
    </header>

    @if (session()->has('journal-entry-message'))
        <p class="journal-entry-feedback" role="status">{{ session('journal-entry-message') }}</p>
    @endif

    @can('accounting.create-journal-entry')
        @if ($accounts->isEmpty())
            <p class="journal-history-empty">No approved active accounts are available. Ask an administrator to approve accounts before creating a journal entry.</p>
        @else
        <div class="mb-4 flex items-center justify-between gap-4 rounded-xl border border-emerald-100 bg-white p-4 max-sm:flex-col max-sm:items-stretch">
            <p class="m-0 text-[13px] text-slate-500">Start a manual journal draft, add balanced account lines, then submit it for approval.</p>
            <button class="journal-save-button" type="button" wire:click="openNewDraft">New manual journal draft</button>
        </div>
        @if ($showEntryModal)
        <div class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" wire:click.self="closeEntryModal" wire:keydown.escape.window="closeEntryModal">
        <section class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none" role="dialog" aria-modal="true" aria-labelledby="journal-entry-modal-heading">
        <form class="journal-entry-card !m-0 !border-0 !shadow-none !p-[22px] max-sm:!p-[18px_14px]" wire:submit="{{ $isCorrecting ? 'correct('.$correctingJournalId.')' : 'saveDraft' }}">
            <header class="mb-[18px] flex items-start justify-between gap-4">
                <div><h2 id="journal-entry-modal-heading" class="mb-1 text-base">{{ $isCorrecting ? 'Correct posted journal' : ($editingDraftId ? 'Edit journal draft' : 'New manual journal draft') }}</h2>
                    <p class="m-0 text-xs text-slate-500">Enter the transaction details and make sure debits equal credits before saving.</p></div>
                <button class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700" type="button" wire:click="closeEntryModal" aria-label="Close journal entry">&times;</button>
            </header>
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
                <div><h3>Account lines</h3><p>Use approved active accounts. Accounts Payable and Inventory require their controlled schedules.</p></div>
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
                                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach
                            </select>
                        </td>
                        <td><label class="sr-only" for="journal-description-{{ $index }}">Line description, line {{ $index + 1 }}</label><input id="journal-description-{{ $index }}" type="text" wire:model="lines.{{ $index }}.description" maxlength="255" placeholder="Optional detail"></td>
                        <td><label class="sr-only" for="journal-debit-{{ $index }}">Debit amount, line {{ $index + 1 }}</label><input id="journal-debit-{{ $index }}" type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.debit" placeholder="0.00"></td>
                        <td><label class="sr-only" for="journal-credit-{{ $index }}">Credit amount, line {{ $index + 1 }}</label><input id="journal-credit-{{ $index }}" type="number" min="0" step="0.01" inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.credit" placeholder="0.00"></td>
                        <td class="journal-line-action">
                            <button class="journal-remove-button" type="button" wire:click="removeLine({{ $index }})" aria-label="Remove line {{ $index + 1 }}" @disabled(count($lines) <= 2)>Remove</button>
                            @error("lines.{$index}") <small class="settings-error journal-line-error">{{ $message }}</small> @enderror
                        </td>
                    </tr>
                @endforeach
                    </tbody>
                </table>
            </div>
            <div class="journal-entry-totals {{ $isBalanced ? 'is-balanced' : 'is-unbalanced' }}" aria-live="polite" aria-atomic="true">
                <div><span>Debit total</span><strong>₱{{ number_format($debitTotal, 2) }}</strong></div>
                <div><span>Credit total</span><strong>₱{{ number_format($creditTotal, 2) }}</strong></div>
                <div class="journal-balance-state"><strong>{{ $isBalanced ? 'Balanced' : 'Not balanced' }}</strong>@unless ($isBalanced)<span>Debits and credits must match before this entry can be submitted.</span>@endunless</div>
            </div>
            @error('lines') <p class="settings-error journal-total-error">{{ $message }}</p> @enderror
            @error('journal') <p class="settings-error journal-total-error">{{ $message }}</p> @enderror
            @if ($isCorrecting)
                <button class="journal-save-button" type="submit" @disabled(auth()->user()->cannot('accounting.post-journal-entry'))>Post linked reversal and replacement</button>
            @else
                <button class="journal-save-button" type="submit">Save draft</button>
            @endif
        </form>
        </section>
        </div>
        @endif
        @endif
    @endcan

    <section class="journal-history-card" aria-labelledby="journal-history-heading">
        <header><div><h2 id="journal-history-heading">Journal register</h2><p>Draft and pending-review entries do not affect posted balances.</p></div></header>
        <div class="journal-register-toolbar">
            <label class="journal-register-search"><span>Search journal entries</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Reference or description"></label>
            <details class="journal-entry-filters">
                <summary>More filters</summary>
                <div class="journal-entry-header-fields">
                    <label><span>From</span><input type="date" wire:model.live="dateFrom"></label>
                    <label><span>To</span><input type="date" wire:model.live="dateTo"></label>
                    <label><span>Source</span><select wire:model.live="sourceFilter"><option value="">All sources</option><option value="manual">Manual</option><option value="reversal">Reversal</option><option value="replacement">Replacement</option><option value="opening">Opening</option></select></label>
                    <label><span>Entry type</span><select wire:model.live="entryTypeFilter"><option value="">All entries</option><option value="standard">Standard</option><option value="adjusting">Adjusting</option></select></label>
                </div>
            </details>
        </div>
        @if ($journals->isNotEmpty())
        <div class="journal-register-scroll"><table class="journal-register-table"><thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Description / lines</th><th scope="col">Status</th><th scope="col" class="journal-register-amount">Debit</th><th scope="col" class="journal-register-amount">Credit</th><th scope="col">Actions</th></tr></thead><tbody>
        @foreach ($journals as $journal)
            <tr wire:key="journal-{{ $journal->id }}">
                <td class="journal-register-date">{{ $journal->accounting_date->format('Y-m-d') }}<span>{{ ucfirst($journal->source_type) }}@if ($journal->entry_type === 'adjusting') · Adjusting @endif</span></td>
                <th scope="row" class="journal-register-reference">{{ $journal->reference }}
                    @if ($journal->external_reference)<span>Ext: {{ $journal->external_reference }}</span>@endif
                    @if ($journal->correction_of_id)<span>Corrects {{ $journal->correctionOf?->reference }}</span>@endif
                </th>
                <td class="journal-register-description"><strong>{{ $journal->description }}</strong>@if ($journal->entry_type === 'adjusting') <span class="journal-entry-adjusted-badge">Adjusting entry</span>@endif
                    <details class="journal-register-lines"><summary>{{ $journal->lines->count() }} account {{ $journal->lines->count() === 1 ? 'line' : 'lines' }} · View lines</summary>
                        <div class="journal-history-lines">
                            @foreach ($journal->lines as $line)
                                <div><span>{{ $line->account->code }} · {{ $line->account->name }}@if ($line->description) — {{ $line->description }}@endif</span><span>Dr {{ number_format($line->debit_cents / 100, 2) }} · Cr {{ number_format($line->credit_cents / 100, 2) }}</span></div>
                            @endforeach
                        </div>
                    </details>
                </td>
                <td><span class="journal-register-status status-{{ $journal->status }}">{{ ucwords(str_replace('_', ' ', $journal->status)) }}</span></td>
                <td class="journal-register-amount">₱{{ number_format($journal->lines->sum('debit_cents') / 100, 2) }}</td>
                <td class="journal-register-amount">₱{{ number_format($journal->lines->sum('credit_cents') / 100, 2) }}</td>
                <td><div class="journal-register-actions">
                    <button type="button" wire:click="showJournal({{ $journal->id }})">Detail</button>
                    @if ($journal->status === 'draft' && $journal->source_type === 'manual')
                        @can('accounting.create-journal-entry')
                            <button type="button" wire:click="editDraft({{ $journal->id }})">Edit</button>
                            <button type="button" wire:click="deleteDraft({{ $journal->id }})">Delete</button>
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

    @if ($selectedJournal)
        <div class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" wire:click.self="$set('selectedJournalId', null)" wire:keydown.escape.window="$set('selectedJournalId', null)">
            <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none max-sm:p-4" role="dialog" aria-modal="true" aria-labelledby="journal-detail-heading">
                <header class="mb-5 flex items-start justify-between gap-4">
                    <div class="min-w-0"><h2 id="journal-detail-heading" class="m-0 break-words text-base font-semibold text-slate-800">{{ $selectedJournal->reference }}</h2>
                        <p class="mb-0 mt-1 text-xs text-slate-500">{{ $selectedJournal->accounting_date->format('F j, Y') }} · {{ ucfirst($selectedJournal->source_type) }}{{ $selectedJournal->entry_type === 'adjusting' ? ' · Adjusting entry' : '' }}</p></div>
                    <button class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700" type="button" wire:click="$set('selectedJournalId', null)" aria-label="Close journal details">&times;</button>
                </header>
                <p class="mb-5 text-sm text-slate-700">{{ $selectedJournal->description }}</p>
                <dl class="mb-5 grid grid-cols-2 gap-x-6 gap-y-4 rounded-lg bg-slate-50 p-4 text-xs max-sm:grid-cols-1">
                    <div><dt class="text-slate-500">Status</dt><dd class="mt-1 font-medium text-slate-800">{{ ucwords(str_replace('_', ' ', $selectedJournal->status)) }}</dd></div>
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
