<article id="disclosure-report" class="disclosure-page" aria-labelledby="accounting-notes-heading">
    <header class="page-heading disclosure-heading">
        <div>
            <h1 id="accounting-notes-heading">Notes to Financial Statements / Disclosure</h1>
            <p class="page-subtitle">Generated figures are derived from the adjusted trial balance. Explanatory note text is reviewed, approved, and protected before it accompanies the financial statements.</p>
        </div>
        <button class="vat-detail-button" type="button" onclick="window.print()">Print with financial statements</button>
    </header>

    @if (session()->has('disclosure-message'))
        <p class="disclosure-feedback" role="status">{{ session('disclosure-message') }}</p>
    @endif

    @error('disclosure')
        <p class="disclosure-feedback disclosure-error" role="alert">{{ $message }}</p>
    @enderror

    <p class="disclosure-disclaimer" role="note">
        Management-prepared disclosures generated from the adjusted trial balance. These notes are not audited,
        examined, or certified by a CPA. Generated and manually written content are labeled separately.
    </p>

    <div class="disclosure-period-controls" role="group" aria-label="Reporting period">
        <label><span>Reporting period from</span><input type="date" wire:model.live="fromDate">@error('fromDate') <small class="settings-error">{{ $message }}</small> @enderror</label>
        <label><span>Reporting period to</span><input type="date" wire:model.live="toDate">@error('toDate') <small class="settings-error">{{ $message }}</small> @enderror</label>
    </div>

    @if ($canManage)
        <div class="disclosure-workflow-actions">
            <div><strong>Prepare disclosures</strong><span>Generated notes capture an approved ledger figure; manual notes add explanatory context.</span></div>
            <button type="button" wire:click="openEditor">New manual note</button>
        </div>
    @endif

    <section class="disclosure-generated" aria-labelledby="disclosure-generated-heading">
        <header>
            <h2 id="disclosure-generated-heading">Generated figures</h2>
            <p>Source: approved FCDC posted activity and adjusting entries through {{ $toDate !== '' ? $toDate : 'Not selected' }} (Asia/Manila).</p>
        </header>
        @if (! $figures['available'])
            <div class="disclosure-empty" role="alert">
                <p>Generated figures unavailable</p>
                <span>{{ $figures['error'] }}</span>
            </div>
        @else
            @error('generated') <p class="settings-error">{{ $message }}</p> @enderror
            <div class="disclosure-metric-grid">
                @foreach ($figures['metrics'] as $metric => $row)
                    <div class="disclosure-metric">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ number_format($row['amount_cents'] / 100, 2) }}</strong>
                        <small>Auto-generated · adjusted trial balance</small>
                        @if ($canManage)
                            <button type="button" wire:click="addGeneratedNote('{{ $metric }}')">Create note</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($canManage && $showEditor)
        <div class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" wire:click.self="closeEditor" wire:keydown.escape.window="closeEditor">
        <section class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none max-sm:p-4" role="dialog" aria-modal="true" aria-labelledby="disclosure-editor-heading">
        <form class="disclosure-editor !m-0 !border-0 !p-0 !shadow-none" wire:submit="saveNote" aria-labelledby="disclosure-editor-heading">
            <header class="mb-4 flex items-start justify-between gap-4">
                <div><h2 id="disclosure-editor-heading">{{ $editingNoteId ? 'Edit disclosure draft' : 'New manual disclosure note' }}</h2>
                    <p class="m-0 text-xs text-slate-500">Save as a draft first; submit it for an independent review when ready.</p></div>
                <button class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50" type="button" wire:click="closeEditor" aria-label="Close disclosure editor">&times;</button>
            </header>
            <p class="disclosure-editor-type">
                Type: <strong>{{ $editingNoteId ? ucfirst($disclosureType) : 'Manual' }}</strong>
                @if ($editingNoteId && $disclosureType === 'generated')
                    · generated note; the captured figure is preserved.
                @endif
            </p>
            <div class="disclosure-editor-fields">
                <label><span>Note reference</span><input type="text" wire:model="noteReference" maxlength="40" placeholder="Note 1">@error('noteReference') <small class="settings-error">{{ $message }}</small> @enderror</label>
                <label><span>Title</span><input type="text" wire:model="title" maxlength="160">@error('title') <small class="settings-error">{{ $message }}</small> @enderror</label>
                <label class="disclosure-editor-body"><span>Explanatory text</span><textarea wire:model="body" rows="4" maxlength="5000"></textarea>@error('body') <small class="settings-error">{{ $message }}</small> @enderror</label>
            </div>
            <div class="disclosure-editor-actions">
                <button type="submit">Save draft</button>
                @if ($editingNoteId)
                    <button type="button" class="disclosure-secondary-button" wire:click="cancelEdit">Cancel</button>
                @endif
            </div>
        </form>
        </section>
        </div>
    @endif

    <section class="disclosure-list" aria-labelledby="disclosure-list-heading">
        <header><h2 id="disclosure-list-heading">Disclosure notes</h2></header>
        @forelse ($notes as $note)
            <article class="disclosure-note" wire:key="disclosure-note-{{ $note->id }}">
                <div class="disclosure-note-head">
                    <div>
                        <strong>{{ $note->note_reference }}</strong>
                        <span class="disclosure-badge disclosure-badge-{{ $note->disclosure_type }}">{{ ucfirst($note->disclosure_type) }}</span>
                        <span class="disclosure-status">{{ ucwords(str_replace('_', ' ', $note->status)) }}</span>
                        <h3>{{ $note->title }}</h3>
                    </div>
                    <div class="disclosure-note-meta">
                        <span>{{ $note->reporting_period_start->format('M j, Y') }} – {{ $note->reporting_period_end->format('M j, Y') }}</span>
                        @if ($note->isGenerated())
                            <span>{{ $metricLabels[$note->source_metric] ?? $note->source_metric }}:
                                {{ $note->generated_amount_cents !== null ? number_format($note->generated_amount_cents / 100, 2) : '—' }}
                            </span>
                        @endif
                    </div>
                </div>
                @if ($note->body)
                    <p class="disclosure-note-body">{{ $note->body }}</p>
                @endif
                <p class="disclosure-note-audit">
                    Prepared by {{ $note->preparer?->username ?? '—' }}
                    @if ($note->approver) · Approved by {{ $note->approver->username }} @if ($note->approved_at) on {{ $note->approved_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} @endif @endif
                    @if ($note->finalizer) · Finalized by {{ $note->finalizer->username }} @if ($note->finalized_at) on {{ $note->finalized_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} @endif @endif
                </p>
                <div class="disclosure-note-actions">
                    @if ($note->status === 'draft')
                        @if ($canManage)
                            <button type="button" wire:click="editNote({{ $note->id }})">Edit</button>
                            <button type="button" wire:click="deleteNote({{ $note->id }})">Delete</button>
                            <button type="button" wire:click="submitNote({{ $note->id }})">Submit for approval</button>
                        @endif
                    @elseif ($note->status === 'pending_review')
                        @if ($canApprove)
                            <button type="button" wire:click="approveNote({{ $note->id }})">Approve</button>
                            <button type="button" wire:click="returnNote({{ $note->id }})">Return to draft</button>
                        @endif
                    @elseif ($note->status === 'approved')
                        @if ($canApprove)
                            <button type="button" wire:click="finalizeNote({{ $note->id }})">Finalize</button>
                        @endif
                    @else
                        <span class="disclosure-protected">Protected · finalized disclosures cannot be edited.</span>
                    @endif
                </div>
            </article>
        @empty
            <p class="disclosure-empty" role="status">No disclosure notes have been prepared. Create a generated note from a figure above or add a manual note.</p>
        @endforelse
    </section>

    <p class="disclosure-generated-at">Generated {{ $generatedAt }}.</p>
</article>
