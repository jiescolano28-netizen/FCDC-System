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
        <form class="journal-entry-card" wire:submit="{{ $isCorrecting ? 'correct('.$correctingJournalId.')' : 'saveDraft' }}">
            <h2>{{ $isCorrecting ? 'Correct posted journal' : ($editingDraftId ? 'Edit journal draft' : 'New manual journal draft') }}</h2>
            <div class="journal-entry-header-fields">
                <label><span>Accounting date</span><input type="date" wire:model="accountingDate">@error('accountingDate') <small class="settings-error">{{ $message }}</small> @enderror</label>
                <label><span>Journal reference</span><input type="text" wire:model="reference" maxlength="80">@error('reference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                <label><span>External reference</span><input type="text" wire:model="externalReference" maxlength="120">@error('externalReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                <label class="journal-entry-description"><span>Description</span><input type="text" wire:model="description" maxlength="255">@error('description') <small class="settings-error">{{ $message }}</small> @enderror</label>
            </div>

            @if ($isCorrecting)
                <div class="journal-entry-header-fields">
                    <label><span>Correction reason</span><input type="text" wire:model="correctionReason" maxlength="1000">@error('correctionReason') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    <label><span>Reversal reference</span><input type="text" wire:model="correctionReference" maxlength="80">@error('correctionReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                    <label><span>Replacement reference</span><input type="text" wire:model="replacementReference" maxlength="80"></label>
                    @error('correctionReference') <small class="settings-error">{{ $message }}</small> @enderror
                </div>
            @endif

            <div class="journal-lines-heading">
                <div><h3>Account lines</h3><p>Use approved active accounts. Accounts Payable and Inventory require their controlled schedules.</p></div>
                <button class="journal-secondary-button" type="button" wire:click="addLine" @disabled(count($lines) >= 100)>Add line</button>
            </div>
            <div class="journal-line-list">
                @foreach ($lines as $index => $line)
                    <fieldset class="journal-line" wire:key="journal-line-{{ $index }}">
                        <legend>Line {{ $index + 1 }}</legend>
                        <label class="journal-line-account"><span>Account</span>
                            <select wire:model="lines.{{ $index }}.accountId"><option value="">Select approved account</option>
                                @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach
                            </select>
                        </label>
                        <label class="journal-line-description"><span>Line description</span><input type="text" wire:model="lines.{{ $index }}.description" maxlength="255"></label>
                        <label><span>Debit (PHP)</span><input type="number" min="0" step="0.01" wire:model="lines.{{ $index }}.debit"></label>
                        <label><span>Credit (PHP)</span><input type="number" min="0" step="0.01" wire:model="lines.{{ $index }}.credit"></label>
                        <button class="journal-remove-button" type="button" wire:click="removeLine({{ $index }})" @disabled(count($lines) <= 2)>Remove line</button>
                        @error("lines.{$index}") <small class="settings-error journal-line-error">{{ $message }}</small> @enderror
                    </fieldset>
                @endforeach
            </div>
            @error('lines') <p class="settings-error journal-total-error">{{ $message }}</p> @enderror
            @error('journal') <p class="settings-error journal-total-error">{{ $message }}</p> @enderror
            @if ($isCorrecting)
                <button class="journal-save-button" type="submit" @disabled(auth()->user()->cannot('accounting.post-journal-entry'))>Post linked reversal and replacement</button>
            @else
                <button class="journal-save-button" type="submit">Save draft</button>
            @endif
        </form>
    @endcan

    <section class="journal-history-card" aria-labelledby="journal-history-heading">
        <header><div><h2 id="journal-history-heading">Journal register</h2><p>Posted entries are immutable; drafts have no accounting effect.</p></div></header>
        <div class="journal-entry-header-fields">
            <label><span>Search</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Reference, description or external reference"></label>
            <label><span>From</span><input type="date" wire:model.live="dateFrom"></label>
            <label><span>To</span><input type="date" wire:model.live="dateTo"></label>
            <label><span>Source</span><select wire:model.live="sourceFilter"><option value="">All sources</option><option value="manual">Manual</option><option value="reversal">Reversal</option><option value="replacement">Replacement</option><option value="opening">Opening</option></select></label>
        </div>
        @forelse ($journals as $journal)
            <article class="journal-history-entry" wire:key="journal-{{ $journal->id }}">
                <div class="journal-history-summary">
                    <div><strong>{{ $journal->reference }}</strong><span>{{ $journal->accounting_date->format('Y-m-d') }} · {{ $journal->source_type }} · {{ $journal->description }}</span>
                        @if ($journal->external_reference)<span>External reference: {{ $journal->external_reference }}</span>@endif
                        @if ($journal->correction_of_id)<span>Corrects <button type="button" wire:click="showJournal({{ $journal->correction_of_id }})">{{ $journal->correctionOf?->reference }}</button> · {{ $journal->correction_reason }}</span>@endif
                    </div>
                    <span class="journal-demo-status">{{ ucfirst($journal->status) }}@if ($journal->poster) · Posted by {{ $journal->poster->username }}@endif</span>
                </div>
                <div class="journal-history-lines">
                    @foreach ($journal->lines as $line)
                        <div><span>{{ $line->account->code }} · {{ $line->account->name }}@if ($line->description) — {{ $line->description }}@endif</span><span>Debit {{ number_format($line->debit_cents / 100, 2) }} · Credit {{ number_format($line->credit_cents / 100, 2) }}</span></div>
                    @endforeach
                </div>
                <div class="journal-entry-actions">
                    <button type="button" wire:click="showJournal({{ $journal->id }})">Detail</button>
                    @if ($journal->status === 'draft')
                        @can('accounting.create-journal-entry')<button type="button" wire:click="editDraft({{ $journal->id }})">Edit draft</button><button type="button" wire:click="deleteDraft({{ $journal->id }})">Delete draft</button>@endcan
                        @can('accounting.post-journal-entry')<button type="button" wire:click="postDraft({{ $journal->id }})">Post draft</button>@endcan
                    @elseif (in_array($journal->source_type, ['manual', 'replacement'], true))
                        @can('accounting.create-journal-entry')<button type="button" wire:click="beginCorrection({{ $journal->id }})">Correct</button>@endcan
                    @endif
                </div>
            </article>
        @empty
            <p class="journal-history-empty">No journal entries match these filters.</p>
        @endforelse
    </section>

    @if ($selectedJournal)
        <section class="journal-history-card" aria-labelledby="journal-detail-heading">
            <h2 id="journal-detail-heading">Journal detail: {{ $selectedJournal->reference }}</h2>
            <p>{{ $selectedJournal->accounting_date->format('Y-m-d') }} · {{ $selectedJournal->source_type }} · {{ $selectedJournal->description }}</p>
            <p>Status: {{ ucfirst($selectedJournal->status) }} · Prepared by {{ $selectedJournal->preparer?->username ?? '—' }} · Posted by {{ $selectedJournal->poster?->username ?? '—' }}</p>
            @if ($selectedJournal->correction_reason)<p>Correction reason: {{ $selectedJournal->correction_reason }}</p>@endif
            <button type="button" wire:click="$set('selectedJournalId', null)">Close detail</button>
        </section>
    @endif
</section>
