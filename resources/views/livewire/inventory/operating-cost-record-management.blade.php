<section class="inventory-page operating-cost-page" aria-labelledby="operating-cost-heading">
    <header class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 id="operating-cost-heading">Operating Cost Record</h1>
            <p class="muted">Record batch reworking costs, including the input VAT used by VAT Compliance, and day-to-day operating expenses.</p>
            <p class="muted">These registers support cost monitoring only; they do not post entries to the Accounting module.</p>
        </div>
        <div class="actions">
            <span class="muted">{{ $batchSummary['count'] }} batch record(s) · {{ $summary['count'] }} expense record(s)</span>
        </div>
    </header>

    @if ($statusMessage)
        <p class="operating-cost-flash" role="status">{{ $statusMessage }}</p>
    @endif

    <nav class="operating-cost-tabs" role="tablist" aria-label="Operating cost record sections">
        <button
            class="operating-cost-tab {{ $activeTab === 'batch' ? 'is-active' : '' }}"
            type="button"
            role="tab"
            wire:click="setTab('batch')"
            aria-selected="{{ $activeTab === 'batch' ? 'true' : 'false' }}"
        >
            Batch / Reworking Cost
        </button>
        <button
            class="operating-cost-tab {{ $activeTab === 'expenses' ? 'is-active' : '' }}"
            type="button"
            role="tab"
            wire:click="setTab('expenses')"
            aria-selected="{{ $activeTab === 'expenses' ? 'true' : 'false' }}"
        >
            Operating Expenses
        </button>
    </nav>

    <div
        role="tabpanel"
        aria-label="Batch and reworking cost records"
        @if ($activeTab !== 'batch') style="display:none" @endif
    >
        <div class="columns" style="justify-content:space-between; margin-bottom:12px;">
            <div>
                <h2 class="operating-cost-section-title">Batch / Reworking Cost</h2>
                <p class="muted">Track cleaning, repair, and preparation costs for each demolition material batch. Saved input VAT here is the source used by the VAT Compliance module.</p>
            </div>
            <div class="actions">
                @can('inventory.create')
                    <button class="primary" type="button" wire:click="createBatch">Add batch record</button>
                @endcan
            </div>
        </div>

        <div class="operating-cost-summary" aria-label="Batch reworking cost summary">
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Recorded batches</span>
                <strong class="operating-cost-stat-value">{{ $batchSummary['count'] }}</strong>
                <span class="operating-cost-stat-note">Batch reworking cost records</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Total reworking cost</span>
                <strong class="operating-cost-stat-value">₱{{ number_format($batchSummary['total'], 2) }}</strong>
                <span class="operating-cost-stat-note">Cleaning, repair, and preparation</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Input VAT</span>
                <strong class="operating-cost-stat-value">₱{{ number_format($batchSummary['vat'], 2) }}</strong>
                <span class="operating-cost-stat-note">VAT shown on supporting documents</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Total including VAT</span>
                <strong class="operating-cost-stat-value">₱{{ number_format($batchSummary['total_with_vat'], 2) }}</strong>
                <span class="operating-cost-stat-note">Reworking cost plus recorded VAT</span>
            </article>
        </div>

        @canany(['inventory.create', 'inventory.update'])
            <dialog
                class="employee-modal operating-cost-modal"
                id="operating-cost-batch-modal"
                wire:ignore.self
                x-data
                x-on:operating-cost-batch-form-open.window="$el.showModal()"
                x-on:operating-cost-batch-form-close.window="$el.close()"
                x-on:click.self="$el.close()"
                x-on:close="$wire.resetBatchForm()"
                aria-labelledby="operating-cost-batch-modal-title"
            >
                <div class="employee-modal-header">
                    <div>
                        <h2 id="operating-cost-batch-modal-title">{{ $batchEditingId ? 'Edit batch reworking cost record' : 'New batch reworking cost record' }}</h2>
                        <p class="muted">Link cleaning, repair, or preparation costs to a demolition project and batch.</p>
                    </div>
                    <button class="secondary" type="button" x-on:click="$el.closest('dialog').close()" aria-label="Close dialog">×</button>
                </div>

                <form class="operating-cost-form" wire:submit="saveBatch">
                    <div class="inventory-form-fields">
                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-project">Demolition project</label>
                            @if ($projects->isNotEmpty())
                                <select id="operating-cost-batch-project" wire:model="projectId">
                                    <option value="">Select project</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->code }} · {{ $project->name }}</option>
                                    @endforeach
                                </select>
                                @error('projectId') <span class="error">{{ $message }}</span> @enderror
                            @else
                                <input id="operating-cost-batch-project" wire:model="projectName" placeholder="e.g. Warehouse Dismantling">
                                <span class="operating-cost-hint">No demolition projects are recorded yet. Enter the project name; it will be stored with this record.</span>
                                @error('projectName') <span class="error">{{ $message }}</span> @enderror
                            @endif
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-number">Batch number</label>
                            <input id="operating-cost-batch-number" wire:model="batchNumber" maxlength="100" placeholder="e.g. BATCH-2026-001">
                            <span class="operating-cost-hint">Use the identifier on the batch label or source document.</span>
                            @error('batchNumber') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-date">Date</label>
                            <input id="operating-cost-batch-date" type="date" wire:model="batchDate">
                            @error('batchDate') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-cost">Total reworking cost (PHP)</label>
                            <input id="operating-cost-batch-cost" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="totalReworkingCost">
                            @error('totalReworkingCost') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-input-vat">Input VAT (PHP)</label>
                            <input id="operating-cost-batch-input-vat" type="number" min="0" step="0.01" wire:model.live.debounce.300ms="inputVat">
                            <span class="operating-cost-hint">Enter the VAT amount shown on the supporting document. This feeds VAT Compliance.</span>
                            @error('inputVat') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-batch-payment-method">Payment method</label>
                            <select id="operating-cost-batch-payment-method" wire:model="batchPaymentMethod">
                                <option value="">Not recorded</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank transfer</option>
                                <option value="Card">Card</option>
                                <option value="Accounts Payable">Accounts payable</option>
                            </select>
                            <span class="operating-cost-hint">Required before Accounting can generate the credit side of the journal.</span>
                            @error('batchPaymentMethod') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field operating-cost-field-wide">
                            <label for="operating-cost-batch-description">Description</label>
                            <textarea id="operating-cost-batch-description" wire:model="batchDescription" maxlength="2000" placeholder="e.g. Cleaning, sorting, and minor repair of recovered steel bars"></textarea>
                            @error('batchDescription') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <p class="operating-cost-form-total">
                            <span>Total including VAT</span>
                            <strong>₱{{ number_format((float) ($totalReworkingCost ?: 0) + (float) ($inputVat ?: 0), 2) }}</strong>
                        </p>

                        <div class="inventory-form-field operating-cost-field-wide">
                            <label for="operating-cost-batch-attachment">Supporting document</label>
                            <input id="operating-cost-batch-attachment" type="file" accept="application/pdf,.pdf"
                                wire:model="batchAttachmentFile" wire:loading.attr="disabled" wire:target="batchAttachmentFile">
                            <span class="operating-cost-hint">PDF only · maximum 10 MB per file.</span>
                            @error('batchAttachmentFile') <span class="error">{{ $message }}</span> @enderror
                            <span class="operating-cost-hint" wire:loading wire:target="batchAttachmentFile">Uploading document…</span>

                            @if ($batchAttachmentFile)
                                <span class="operating-cost-hint">
                                    Selected: {{ $batchAttachmentFile->getClientOriginalName() }}
                                    ({{ number_format($batchAttachmentFile->getSize() / 1048576, 2) }} MB)
                                </span>
                            @endif

                            @if ($existingBatchAttachment)
                                <div class="operating-cost-existing-file">
                                    <span class="operating-cost-file-badge">PDF</span>
                                    <span class="operating-cost-file-name" title="{{ $existingBatchAttachment['name'] }}">{{ $existingBatchAttachment['name'] }}</span>
                                    <span class="muted">{{ number_format(($existingBatchAttachment['size'] ?? 0) / 1048576, 2) }} MB</span>
                                    @if ($batchEditingId && ($batchAttachmentUrls[$batchEditingId] ?? null))
                                        <a class="secondary-link" href="{{ $batchAttachmentUrls[$batchEditingId] }}" target="_blank" rel="noopener">Download PDF</a>
                                    @endif
                                    @if ($removeExistingBatchAttachment)
                                        <span class="muted">Will be removed on save.</span>
                                        <button type="button" class="secondary" wire:click="$set('removeExistingBatchAttachment', false)">Keep</button>
                                    @else
                                        <span class="muted">{{ $batchAttachmentFile ? 'A new file will replace this document on save.' : 'Current supporting document.' }}</span>
                                        <button type="button" class="secondary" wire:click="$set('removeExistingBatchAttachment', true)">Remove</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="actions employee-modal-actions">
                        <button class="secondary" type="button" x-on:click="$el.closest('dialog').close()">Cancel</button>
                        <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="saveBatch,batchAttachmentFile">
                            {{ $batchEditingId ? 'Save changes' : 'Add record' }}
                        </button>
                    </div>
                </form>
            </dialog>
        @endcanany

        <div class="toolbar panel inventory-toolbar">
            <div class="inventory-search">
                <label for="operating-cost-batch-search">Search batch records</label>
                <input id="operating-cost-batch-search" type="search" placeholder="Project, batch number, or description" wire:model.live.debounce.300ms="batchSearch">
            </div>
            <span class="muted">{{ $batchRecords->count() }} matching record(s)</span>
        </div>

        <div class="panel table-wrap">
            @php($hasBatchActions = auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete'))
            <table class="operating-cost-table">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Batch number</th>
                        <th scope="col">Date</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="numeric">Total reworking cost</th>
                        <th scope="col" class="numeric">Input VAT</th>
                        <th scope="col">Payment method</th>
                        <th scope="col">Supporting document</th>
                        @if ($hasBatchActions)
                            <th scope="col">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batchRecords as $record)
                        <tr wire:key="operating-cost-batch-record-{{ $record['id'] }}">
                            <td><strong>{{ $record['project'] }}</strong></td>
                            <td><span class="status">{{ $record['batch_number'] }}</span></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($record['date'])->format('M j, Y') }}</td>
                            <td><span class="operating-cost-description" title="{{ $record['description'] }}">{{ $record['description'] }}</span></td>
                            <td class="numeric">₱{{ number_format((float) $record['total_reworking_cost'], 2) }}</td>
                            <td class="numeric">₱{{ number_format((float) $record['input_vat'], 2) }}</td>
                            <td>{{ $record['payment_method'] ?: 'Not recorded' }}</td>
                            <td>
                                @if ($record['attachment'] ?? null)
                                    <div class="operating-cost-attachment">
                                        <span class="operating-cost-file-badge">PDF</span>
                                        <span class="operating-cost-file-name" title="{{ $record['attachment']['name'] }}">{{ $record['attachment']['name'] }}</span>
                                        <span class="muted">{{ number_format(($record['attachment']['size'] ?? 0) / 1048576, 2) }} MB</span>
                                        @if ($batchAttachmentUrls[$record['id']] ?? null)
                                            <a class="secondary-link" href="{{ $batchAttachmentUrls[$record['id']] }}" target="_blank" rel="noopener">Download PDF</a>
                                        @else
                                            <span class="muted">Unavailable</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="operating-cost-no-file">No document</span>
                                @endif
                            </td>
                            @if ($hasBatchActions)
                                <td>
                                    <div class="actions">
                                        @can('inventory.update')
                                            <button class="secondary" type="button" wire:click="editBatch({{ $record['id'] }})">Edit</button>
                                        @endcan
                                        @can('inventory.delete')
                                            <button class="danger" type="button" wire:click="deleteBatch({{ $record['id'] }})"
                                                wire:confirm="Delete this batch reworking cost record? This cannot be undone.">Delete</button>
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $hasBatchActions ? 9 : 8 }}">
                                <div class="operating-cost-empty">
                                    <strong>No batch reworking cost records found.</strong>
                                    <span>Record cleaning, repair, or preparation costs to monitor each batch's actual material cost and input VAT.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div
        role="tabpanel"
        aria-label="Operating expense records"
        @if ($activeTab !== 'expenses') style="display:none" @endif
    >
        <div class="columns" style="justify-content:space-between; margin-bottom:12px;">
            <div>
                <h2 class="operating-cost-section-title">Operating Expenses</h2>
                <p class="muted">Record and monitor the day-to-day operating expenses of the business. These entries are standalone and do not change the input VAT totals in VAT Compliance.</p>
            </div>
            <div class="actions">
                @can('inventory.create')
                    <button class="primary" type="button" wire:click="create">Add expense record</button>
                @endcan
            </div>
        </div>

        <div class="operating-cost-summary" aria-label="Operating cost summary">
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Expense records</span>
                <strong class="operating-cost-stat-value">{{ $summary['count'] }}</strong>
                <span class="operating-cost-stat-note">All saved operating cost records</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">Total operating expenses</span>
                <strong class="operating-cost-stat-value">₱{{ number_format($summary['total'], 2) }}</strong>
                <span class="operating-cost-stat-note">Sum of all recorded amounts</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">This month</span>
                <strong class="operating-cost-stat-value">₱{{ number_format($summary['month'], 2) }}</strong>
                <span class="operating-cost-stat-note">{{ now('Asia/Manila')->format('F Y') }}</span>
            </article>
            <article class="panel operating-cost-stat">
                <span class="operating-cost-stat-label">With receipts</span>
                <strong class="operating-cost-stat-value">{{ $summary['with_documents'] }}</strong>
                <span class="operating-cost-stat-note">Records with a supporting document</span>
            </article>
        </div>

        @canany(['inventory.create', 'inventory.update'])
            <dialog
                class="employee-modal operating-cost-modal"
                id="operating-cost-modal"
                wire:ignore.self
                x-data
                x-on:operating-cost-record-form-open.window="$el.showModal()"
                x-on:operating-cost-record-form-close.window="$el.close()"
                x-on:click.self="$el.close()"
                x-on:close="$wire.resetForm()"
                aria-labelledby="operating-cost-modal-title"
            >
                <div class="employee-modal-header">
                    <div>
                        <h2 id="operating-cost-modal-title">{{ $editingId ? 'Edit operating cost record' : 'New operating cost record' }}</h2>
                        <p class="muted">Enter the expense details and attach the receipt or supporting document if available.</p>
                    </div>
                    <button class="secondary" type="button" x-on:click="$el.closest('dialog').close()" aria-label="Close dialog">×</button>
                </div>

                <form class="operating-cost-form" wire:submit="save">
                    <div class="inventory-form-fields">
                        <div class="inventory-form-field">
                            <label for="operating-cost-date">Expense date</label>
                            <input id="operating-cost-date" type="date" wire:model="expenseDate">
                            @error('expenseDate') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-reference">Reference number</label>
                            <input id="operating-cost-reference" wire:model="referenceNumber" maxlength="100" placeholder="e.g. OR-2026-00125">
                            <span class="operating-cost-hint">Use the receipt, invoice, or voucher number.</span>
                            @error('referenceNumber') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-category">Expense category</label>
                            <select id="operating-cost-category" wire:model="expenseCategory">
                                <option value="">Select expense category</option>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                            @error('expenseCategory') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-payment-method">Payment method</label>
                            <input id="operating-cost-payment-method" wire:model="paymentMethod" maxlength="100" placeholder="e.g. Cash, Bank Transfer, Card, Accounts Payable">
                            <span class="operating-cost-hint">Accounting can generate a journal for cash, bank transfer, card, or accounts payable settlements.</span>
                            @error('paymentMethod') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field">
                            <label for="operating-cost-amount">Amount (PHP)</label>
                            <input id="operating-cost-amount" type="number" min="0.01" step="0.01" wire:model.live.debounce.300ms="amount">
                            @error('amount') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="inventory-form-field operating-cost-field-wide">
                            <label for="operating-cost-description">Description</label>
                            <textarea id="operating-cost-description" wire:model="expenseDescription" maxlength="2000" placeholder="e.g. Monthly electricity bill for the warehouse and office"></textarea>
                            @error('expenseDescription') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <p class="operating-cost-form-total">
                            <span>Recorded amount</span>
                            <strong>₱{{ number_format((float) ($amount ?: 0), 2) }}</strong>
                        </p>

                        <div class="inventory-form-field operating-cost-field-wide">
                            <label for="operating-cost-attachment">Receipt or supporting document (optional)</label>
                            <input id="operating-cost-attachment" type="file" accept="application/pdf,.pdf,image/jpeg,.jpg,.jpeg,image/png,.png"
                                wire:model="attachmentFile" wire:loading.attr="disabled" wire:target="attachmentFile">
                            <span class="operating-cost-hint">PDF, JPG, or PNG · maximum 10 MB per file.</span>
                            @error('attachmentFile') <span class="error">{{ $message }}</span> @enderror
                            <span class="operating-cost-hint" wire:loading wire:target="attachmentFile">Uploading document…</span>

                            @if ($attachmentFile)
                                <span class="operating-cost-hint">
                                    Selected: {{ $attachmentFile->getClientOriginalName() }}
                                    ({{ number_format($attachmentFile->getSize() / 1048576, 2) }} MB)
                                </span>
                            @endif

                            @if ($existingAttachment)
                                @php($existingExtension = strtoupper(pathinfo($existingAttachment['name'], PATHINFO_EXTENSION)) ?: 'FILE')
                                <div class="operating-cost-existing-file">
                                    <span class="operating-cost-file-badge">{{ $existingExtension }}</span>
                                    <span class="operating-cost-file-name" title="{{ $existingAttachment['name'] }}">{{ $existingAttachment['name'] }}</span>
                                    <span class="muted">
                                        @if ($existingAttachment['size'] >= 1048576)
                                            {{ number_format($existingAttachment['size'] / 1048576, 2) }} MB
                                        @else
                                            {{ number_format($existingAttachment['size'] / 1024, 0) }} KB
                                        @endif
                                    </span>
                                    <button type="button" class="secondary" wire:click="downloadAttachment({{ $editingId }})">Download</button>
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
                <label for="operating-cost-search">Search records</label>
                <input id="operating-cost-search" type="search" placeholder="Reference number, category, description, or payment method" wire:model.live.debounce.300ms="search">
            </div>
            <div class="inventory-search operating-cost-filter">
                <label for="operating-cost-category-filter">Expense category</label>
                <select id="operating-cost-category-filter" wire:model.live="categoryFilter">
                    <option value="">All categories</option>
                    @foreach ($expenseCategories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <span class="muted">{{ $records->count() }} matching record(s)</span>
        </div>

        <div class="panel table-wrap">
            @php($hasActions = auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete'))
            <table class="operating-cost-table">
                <thead>
                    <tr>
                        <th scope="col">Expense date</th>
                        <th scope="col">Reference number</th>
                        <th scope="col">Expense category</th>
                        <th scope="col">Description</th>
                        <th scope="col" class="numeric">Amount</th>
                        <th scope="col">Payment method</th>
                        <th scope="col">Receipt</th>
                        @if ($hasActions)
                            <th scope="col">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php($fileExtension = strtoupper(pathinfo($record->attachment_name ?? '', PATHINFO_EXTENSION)) ?: 'FILE')
                        <tr wire:key="operating-cost-record-{{ $record->id }}">
                            <td>{{ $record->expense_date?->format('M j, Y') }}</td>
                            <td><strong>{{ $record->reference_number }}</strong></td>
                            <td><span class="status">{{ $record->expense_category }}</span></td>
                            <td><span class="operating-cost-description" title="{{ $record->description }}">{{ $record->description }}</span></td>
                            <td class="numeric">₱{{ number_format((float) $record->amount, 2) }}</td>
                            <td>{{ $record->payment_method }}</td>
                            <td>
                                @if ($record->attachment_path)
                                    <div class="operating-cost-attachment">
                                        <span class="operating-cost-file-badge">{{ $fileExtension }}</span>
                                        <span class="operating-cost-file-name" title="{{ $record->attachment_name }}">{{ $record->attachment_name }}</span>
                                        <span class="muted">{{ $record->attachment_human_size }}</span>
                                        <button class="secondary-link" type="button" wire:click="downloadAttachment({{ $record->id }})">Download</button>
                                    </div>
                                @else
                                    <span class="operating-cost-no-file">No document</span>
                                @endif
                            </td>
                            @if ($hasActions)
                                <td>
                                    <div class="actions">
                                        @can('inventory.update')
                                            <button class="secondary" type="button" wire:click="edit({{ $record->id }})">Edit</button>
                                        @endcan
                                        @can('inventory.delete')
                                            <button class="danger" type="button" wire:click="delete({{ $record->id }})"
                                                wire:confirm="Delete this operating cost record? This cannot be undone.">Delete</button>
                                        @endcan
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $hasActions ? 8 : 7 }}">
                                <div class="operating-cost-empty">
                                    <strong>No operating cost records found.</strong>
                                    <span>Add an expense record to keep track of the business's day-to-day operating costs.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
