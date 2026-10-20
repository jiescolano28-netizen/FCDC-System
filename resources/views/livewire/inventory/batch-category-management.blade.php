<section class="inventory-page" aria-labelledby="batch-categories-heading">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1 id="batch-categories-heading">Inventory batch categories</h1>
            <p class="muted">Create and manage categories for grouping inventory batches.</p>
        </div>
        @can('inventory.create')
            <button class="primary" type="button" wire:click="create">Add batch category</button>
        @endcan
    </div>

    @canany(['inventory.create', 'inventory.update'])
        <dialog class="employee-modal batch-category-modal" id="batch-category-modal" wire:ignore.self aria-labelledby="batch-category-modal-title">
            <div class="employee-modal-header">
                <div>
                    <h2 id="batch-category-modal-title">{{ $editingId ? 'Edit batch category' : 'New batch category' }}</h2>
                    <p class="muted">Create and manage categories for grouping inventory batches.</p>
                </div>
                <button class="secondary" type="button" data-close-batch-category-modal aria-label="Close dialog">×</button>
            </div>
            <form wire:submit="save">
                <div class="inventory-form-fields">
                    <div class="inventory-form-field">
                        <label for="batch-category-name">Name</label>
                        <input id="batch-category-name" wire:model="name" maxlength="255">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="inventory-form-field">
                        <label for="batch-category-description">Description (optional)</label>
                        <textarea id="batch-category-description" wire:model="description"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="actions employee-modal-actions">
                    <button class="secondary" type="button" data-close-batch-category-modal>Cancel</button>
                    <button class="primary" type="submit">{{ $editingId ? 'Save changes' : 'Create category' }}</button>
                </div>
            </form>
        </dialog>
    @endcanany

    @can('inventory.view')
        <dialog class="employee-modal batch-category-attachments-modal" id="batch-category-attachments-modal" wire:ignore.self aria-labelledby="batch-category-attachments-title">
            <div class="employee-modal-header">
                <div>
                    <h2 id="batch-category-attachments-title">Attachments</h2>
                    <p class="muted">{{ $attachmentsCategory?->name ?? 'Select a batch category' }}</p>
                </div>
                <button class="secondary" type="button" data-close-batch-category-attachments-modal aria-label="Close dialog">×</button>
            </div>

            @if ($attachmentsCategory)
                <section class="receipt-panel" aria-labelledby="batch-category-attachment-files-heading">
                    <header class="receipt-heading">
                        <div>
                            <h2 id="batch-category-attachment-files-heading">Batch category files</h2>
                            <p>Upload reference files for this batch category. Any number of files, up to 10&nbsp;MB each.</p>
                        </div>
                    </header>

                    @can('inventory.update')
                        <form class="inventory-form" wire:submit="uploadAttachments" style="padding:16px 20px; margin:0;">
                            <div class="inventory-form-field">
                                <label for="batch-category-attachment-files">Add files</label>
                                <input id="batch-category-attachment-files" type="file" multiple wire:model="attachmentFiles"
                                    wire:loading.attr="disabled" wire:target="attachmentFiles">
                                @error('attachmentFiles') <span class="error">{{ $message }}</span> @enderror
                                @error('attachmentFiles.*') <span class="error">{{ $message }}</span> @enderror
                            </div>
                            <div class="actions inventory-form-actions">
                                <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="attachmentFiles,uploadAttachments">
                                    <span wire:loading.remove wire:target="uploadAttachments">Upload files</span>
                                    <span wire:loading wire:target="uploadAttachments">Uploading…</span>
                                </button>
                            </div>
                        </form>
                    @endcan

                    <div class="receipt-table-scroll" role="region" aria-label="Batch category attachments" tabindex="0">
                        <table class="receipt-table">
                            <thead>
                                <tr>
                                    <th scope="col">Preview</th>
                                    <th scope="col">File</th>
                                    <th scope="col">Type</th>
                                    <th scope="col" class="numeric">Size</th>
                                    <th scope="col">Uploaded by</th>
                                    <th scope="col">Uploaded</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attachments as $attachment)
                                    <tr wire:key="batch-category-attachment-{{ $attachment->id }}">
                                        <td>
                                            <button type="button" class="receipt-thumb-button" wire:click="previewAttachment({{ $attachment->id }})"
                                                title="Preview {{ $attachment->original_name }}" aria-label="Preview {{ $attachment->original_name }}">
                                                @if ($attachment->isImage())
                                                    <img class="receipt-thumb" src="{{ route('inventory.batch-categories.attachments.preview', $attachment) }}"
                                                        alt="{{ $attachment->original_name }}" loading="lazy">
                                                @else
                                                    <span class="receipt-file-type" aria-hidden="true">{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                                @endif
                                            </button>
                                        </td>
                                        <td>{{ $attachment->original_name }}</td>
                                        <td>{{ $attachment->mime_type ?: '—' }}</td>
                                        <td class="numeric">{{ $attachment->human_size }}</td>
                                        <td>{{ $attachment->uploader?->username ?? '—' }}</td>
                                        <td>{{ $attachment->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td>
                                            <button type="button" class="secondary" wire:click="previewAttachment({{ $attachment->id }})">View</button>
                                            <a class="secondary" href="{{ route('inventory.batch-categories.attachments.download', $attachment) }}">Download</a>
                                            @can('inventory.update')
                                                <button type="button" class="secondary"
                                                    wire:click="deleteAttachment({{ $attachment->id }})"
                                                    wire:confirm="Delete this file? This cannot be undone."
                                                    wire:loading.attr="disabled">Delete</button>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="receipt-empty">No files uploaded for this batch category.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if ($previewAttachment)
                    <div class="vat-modal-backdrop" role="presentation" wire:click.self="closeAttachmentPreview">
                        <section class="vat-detail-modal receipt-preview-modal" role="dialog" aria-modal="true" aria-labelledby="batch-category-attachment-preview-heading">
                            <header>
                                <h2 id="batch-category-attachment-preview-heading">{{ $previewAttachment->original_name }}</h2>
                                <button class="vat-modal-close" type="button" wire:click="closeAttachmentPreview" aria-label="Close preview">&times;</button>
                            </header>
                            @if ($previewAttachment->isImage())
                                <img class="receipt-preview-image"
                                    src="{{ route('inventory.batch-categories.attachments.preview', $previewAttachment) }}"
                                    alt="{{ $previewAttachment->original_name }}">
                            @else
                                <p class="muted">Preview isn’t available for this file type. Use Download to open it.</p>
                            @endif
                            <dl>
                                <div><dt>Type</dt><dd>{{ $previewAttachment->mime_type ?: '—' }}</dd></div>
                                <div><dt>Size</dt><dd>{{ $previewAttachment->human_size }}</dd></div>
                                <div><dt>Uploaded by</dt><dd>{{ $previewAttachment->uploader?->username ?? '—' }}</dd></div>
                                <div><dt>Uploaded</dt><dd>{{ $previewAttachment->created_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                            </dl>
                            <footer>
                                <a class="secondary" href="{{ route('inventory.batch-categories.attachments.download', $previewAttachment) }}">Download</a>
                            </footer>
                        </section>
                    </div>
                @endif
            @endif
        </dialog>
    @endcan


    <div class="toolbar panel inventory-toolbar">
        <div class="inventory-search">
            <label for="batch-category-search">Search categories</label>
            <input id="batch-category-search" type="search" wire:model.live.debounce.300ms="search">
        </div>
        @can('inventory.delete')
            <label><input type="checkbox" wire:model.live="showDeleted"> Show deleted categories</label>
        @endcan
    </div>

    <div class="panel" style="overflow-x:auto;">
        <table>
            <thead><tr><th>Name</th><th>Description</th><th>Created by</th><th>Updated by</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr wire:key="batch-category-{{ $category->id }}">
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->description ?: '—' }}</td>
                        <td>{{ $category->creator?->username ?? '—' }}</td>
                        <td>{{ $category->updater?->username ?? '—' }}</td>
                        <td>
                            <div class="actions">
                                @if ($category->trashed())
                                    @can('inventory.delete')
                                        <button class="secondary" type="button" wire:click="restore({{ $category->id }})">Restore</button>
                                    @endcan
                                @else
                                    <button class="secondary" type="button" wire:click="manageAttachments({{ $category->id }})">Attachments</button>
                                    @can('inventory.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $category->id }})">Edit</button>
                                    @endcan
                                    @can('inventory.delete')
                                        <button class="danger" type="button" wire:click="delete({{ $category->id }})" wire:confirm="Delete {{ $category->name }}?">Delete</button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No batch categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
