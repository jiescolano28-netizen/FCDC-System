<section class="inventory-page reworking-cost-page" aria-labelledby="reworking-cost-heading">
    <header class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 id="reworking-cost-heading">Reworking Cost Record</h1>
            <p class="muted">Track cleaning, repair, and preparation costs for each demolition material batch.</p>
            <p class="muted">These records support cost monitoring only; they do not change inventory valuation or post accounting entries.</p>
        </div>
        <div class="actions">
            <span class="muted">{{ $summary['count'] }} record(s)</span>
            @can('inventory.create')
                <button class="primary" type="button" wire:click="create">Add record</button>
            @endcan
        </div>
    </header>

    @if ($statusMessage)
        <p class="reworking-cost-flash" role="status">{{ $statusMessage }}</p>
    @endif

    <div class="reworking-cost-summary" aria-label="Reworking cost summary">
        <article class="panel reworking-cost-stat">
            <span class="reworking-cost-stat-label">Recorded batches</span>
            <strong class="reworking-cost-stat-value">{{ $summary['count'] }}</strong>
            <span class="reworking-cost-stat-note">Reworking cost records</span>
        </article>
        <article class="panel reworking-cost-stat">
            <span class="reworking-cost-stat-label">Total reworking cost</span>
            <strong class="reworking-cost-stat-value">₱{{ number_format($summary['total'], 2) }}</strong>
            <span class="reworking-cost-stat-note">Cleaning, repair, and preparation</span>
        </article>
        <article class="panel reworking-cost-stat">
            <span class="reworking-cost-stat-label">Input VAT</span>
            <strong class="reworking-cost-stat-value">₱{{ number_format($summary['vat'], 2) }}</strong>
            <span class="reworking-cost-stat-note">VAT shown on supporting documents</span>
        </article>
        <article class="panel reworking-cost-stat">
            <span class="reworking-cost-stat-label">Total including VAT</span>
            <strong class="reworking-cost-stat-value">₱{{ number_format($summary['total_with_vat'], 2) }}</strong>
            <span class="reworking-cost-stat-note">Reworking cost plus recorded VAT</span>
        </article>
    </div>

    @canany(['inventory.create', 'inventory.update'])
        <dialog
            class="employee-modal reworking-cost-modal"
            id="reworking-cost-modal"
            wire:ignore.self
            x-data
            x-on:reworking-cost-record-form-open.window="$el.showModal()"
            x-on:reworking-cost-record-form-close.window="$el.close()"
            x-on:click.self="$el.close()"
            x-on:close="$wire.resetForm()"
            aria-labelledby="reworking-cost-modal-title"
        >
            <div class="employee-modal-header">
                <div>
                    <h2 id="reworking-cost-modal-title">{{ $editingId ? 'Edit reworking cost record' : 'New reworking cost record' }}</h2>
                    <p class="muted">Link cleaning, repair, or preparation costs to a demolition project and batch.</p>
                </div>
                <button class="secondary" type="button" x-on:click="$el.closest('dialog').close()" aria-label="Close dialog">×</button>
            </div>

            <form class="reworking-cost-form" wire:submit="save">
                <div class="inventory-form-fields">
                    <div class="inventory-form-field">
                        <label for="reworking-project">Demolition project</label>
                        @if ($projects->isNotEmpty())
                            <select id="reworking-project" wire:model="projectId">
                                <option value="">Select project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->code }} · {{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('projectId') <span class="error">{{ $message }}</span> @enderror
                        @else
                            <input id="reworking-project" wire:model="projectName" placeholder="e.g. Warehouse Dismantling">
                            <span class="reworking-cost-hint">No demolition projects are recorded yet. Enter the project name; it will be stored with this record.</span>
                            @error('projectName') <span class="error">{{ $message }}</span> @enderror
                        @endif
                    </div>

                    <div class="inventory-form-field">
                        <label for="reworking-batch-number">Batch number</label>
                        <input id="reworking-batch-number" wire:model="batchNumber" maxlength="100" placeholder="e.g. BATCH-2026-001">
                        <span class="reworking-cost-hint">Use the identifier on the batch label or source document.</span>
                        @error('batchNumber') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="inventory-form-field">
                        <label for="reworking-date">Date</label>
                        <input id="reworking-date" type="date" wire:model="recordDate">
                        @error('recordDate') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="inventory-form-field">
                        <label for="reworking-total-cost">Total reworking cost (PHP)</label>
                        <input id="reworking-total-cost" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="totalReworkingCost">
                        @error('totalReworkingCost') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="inventory-form-field">
                        <label for="reworking-input-vat">Input VAT (PHP)</label>
                        <input id="reworking-input-vat" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="inputVat">
                        <span class="reworking-cost-hint">Enter the VAT amount shown on the supporting document.</span>
                        @error('inputVat') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="inventory-form-field reworking-cost-field-wide">
                        <label for="reworking-description">Description</label>
                        <textarea id="reworking-description" wire:model="description" maxlength="2000" placeholder="e.g. Cleaning, sorting, and minor repair of recovered steel bars"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <p class="reworking-cost-form-total">
                        <span>Total including VAT</span>
                        <strong>₱{{ number_format((float) ($totalReworkingCost ?: 0) + (float) ($inputVat ?: 0), 2) }}</strong>
                    </p>

                    <div class="inventory-form-field reworking-cost-field-wide">
                        <label for="reworking-attachment">Supporting document</label>
                        <input id="reworking-attachment" type="file" accept="application/pdf,.pdf"
                            wire:model="attachmentFile" wire:loading.attr="disabled" wire:target="attachmentFile">
                        <span class="reworking-cost-hint">PDF only · maximum 10 MB per file.</span>
                        @error('attachmentFile') <span class="error">{{ $message }}</span> @enderror
                        <span class="reworking-cost-hint" wire:loading wire:target="attachmentFile">Uploading document…</span>

                        @if ($attachmentFile)
                            <span class="reworking-cost-hint">
                                Selected: {{ $attachmentFile->getClientOriginalName() }}
                                ({{ number_format($attachmentFile->getSize() / 1048576, 2) }} MB)
                            </span>
                        @endif

                        @if ($existingAttachment)
                            <div class="reworking-cost-existing-file">
                                <span class="reworking-cost-file-badge">PDF</span>
                                <span class="reworking-cost-file-name" title="{{ $existingAttachment['name'] }}">{{ $existingAttachment['name'] }}</span>
                                <span class="muted">{{ number_format(($existingAttachment['size'] ?? 0) / 1048576, 2) }} MB</span>
                                @if ($editingId && ($attachmentUrls[$editingId] ?? null))
                                    <a class="secondary-link" href="{{ $attachmentUrls[$editingId] }}" target="_blank" rel="noopener">Download PDF</a>
                                @endif
                                @if ($removeExistingAttachment)
                                    <span class="muted">Will be removed on save.</span>
                                    <button type="button" class="secondary" wire:click="$set('removeExistingAttachment', false)">Keep</button>
                                @else
                                    <span class="muted">{{ $attachmentFile ? 'A new file will replace this document on save.' : 'Current supporting document.' }}</span>
                                    <button type="button" class="secondary" wire:click="$set('removeExistingAttachment', true)">Remove</button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="actions employee-modal-actions">
                    <button class="secondary" type="button" x-on:click="$el.closest('dialog').close()">Cancel</button>
                    <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="save,attachmentFile">
                        {{ $editingId ? 'Save changes' : 'Add record' }}
                    </button>
                </div>
            </form>
        </dialog>
    @endcanany

    <div class="toolbar panel inventory-toolbar">
        <div class="inventory-search">
            <label for="reworking-cost-search">Search records</label>
            <input id="reworking-cost-search" type="search" placeholder="Project, batch number, or description" wire:model.live.debounce.300ms="search">
        </div>
        <span class="muted">{{ $records->count() }} matching record(s)</span>
    </div>

    <div class="panel table-wrap">
        @php($hasActions = auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete'))
        <table class="reworking-cost-table">
            <thead>
                <tr>
                    <th scope="col">Project</th>
                    <th scope="col">Batch number</th>
                    <th scope="col">Date</th>
                    <th scope="col">Description</th>
                    <th scope="col" class="numeric">Total reworking cost</th>
                    <th scope="col" class="numeric">Input VAT</th>
                    <th scope="col">Supporting document</th>
                    @if ($hasActions)
                        <th scope="col">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr wire:key="reworking-cost-record-{{ $record['id'] }}">
                        <td><strong>{{ $record['project'] }}</strong></td>
                        <td><span class="status">{{ $record['batch_number'] }}</span></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($record['date'])->format('M j, Y') }}</td>
                        <td><span class="reworking-cost-description" title="{{ $record['description'] }}">{{ $record['description'] }}</span></td>
                        <td class="numeric">₱{{ number_format((float) $record['total_reworking_cost'], 2) }}</td>
                        <td class="numeric">₱{{ number_format((float) $record['input_vat'], 2) }}</td>
                        <td>
                            @if ($record['attachment'] ?? null)
                                <div class="reworking-cost-attachment">
                                    <span class="reworking-cost-file-badge">PDF</span>
                                    <span class="reworking-cost-file-name" title="{{ $record['attachment']['name'] }}">{{ $record['attachment']['name'] }}</span>
                                    <span class="muted">{{ number_format(($record['attachment']['size'] ?? 0) / 1048576, 2) }} MB</span>
                                    @if ($attachmentUrls[$record['id']] ?? null)
                                        <a class="secondary-link" href="{{ $attachmentUrls[$record['id']] }}" target="_blank" rel="noopener">Download PDF</a>
                                    @else
                                        <span class="muted">Unavailable</span>
                                    @endif
                                </div>
                            @else
                                <span class="reworking-cost-no-file">No document</span>
                            @endif
                        </td>
                        @if ($hasActions)
                            <td>
                                <div class="actions">
                                    @can('inventory.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $record['id'] }})">Edit</button>
                                    @endcan
                                    @can('inventory.delete')
                                        <button class="danger" type="button" wire:click="delete({{ $record['id'] }})"
                                            wire:confirm="Delete this reworking cost record? This cannot be undone.">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $hasActions ? 8 : 7 }}">
                            <div class="reworking-cost-empty">
                                <strong>No reworking cost records found.</strong>
                                <span>Record cleaning, repair, or preparation costs to monitor each batch's actual material cost.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
