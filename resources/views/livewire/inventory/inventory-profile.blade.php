<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>{{ $item->name }}</h1>
            <p class="muted">{{ $item->code }} · {{ $item->category }}</p>
        </div>
        <div class="actions">
            <a class="secondary" href="{{ route('inventory.management') }}">Back to inventory</a>
            @if (auth()->user()->can('inventory.update'))
                <a class="primary" href="{{ route('inventory.management.edit', $item->id) }}">Edit item</a>
            @elseif (auth()->user()->can('inventory.movements.record'))
                <a class="primary" href="{{ route('inventory.management.edit', $item->id) }}">Record stock movement</a>
            @endif
        </div>
    </div>

    <section class="panel" aria-labelledby="inventory-details-heading">
        <h2 id="inventory-details-heading">Details</h2>
        <dl class="inventory-detail-grid">
            <div><dt>Code</dt><dd>{{ $item->code }}</dd></div>
            <div><dt>Category</dt><dd>{{ $item->category }}</dd></div>
            <div><dt>Batch category</dt><dd>{{ $item->batchCategory?->name ?? '—' }}</dd></div>
            <div><dt>Quantity (on hand)</dt><dd>{{ number_format((float) $item->qty, 0) }}</dd></div>
            <div><dt>Unit label</dt><dd>{{ filled($item->unit) ? $item->unit : '—' }}</dd></div>
            <div><dt>Unit cost (PHP)</dt><dd>{{ number_format((float) $item->unit_cost, 2) }}</dd></div>
            <div><dt>Selling price (PHP)</dt><dd>{{ $item->selling_price === null ? '—' : number_format((float) $item->selling_price, 2) }}</dd></div>
            <div><dt>Tax classification</dt><dd>{{ $item->taxClassificationLabel() }}</dd></div>
            <div><dt>VAT rate</dt><dd>{{ \App\Support\TaxClassification::rateLabel($item->taxClassification()) }}</dd></div>
            <div><dt>Low stock level</dt><dd>{{ number_format((float) $item->reorder_level, 0) }}</dd></div>
            <div><dt>Status</dt><dd>{{ ucfirst($item->status) }}</dd></div>
            <div><dt>Description</dt><dd>{{ $item->description ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="panel receipt-panel" aria-labelledby="inventory-receipts-heading">
        <header class="receipt-heading">
            <div>
                <h2 id="inventory-receipts-heading">Receipts</h2>
                <p>Review receipt dates, quantities, and posting details for this item.</p>
            </div>
        </header>
        <div class="receipt-table-scroll" role="region" aria-label="Inventory receipts" tabindex="0">
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Receipt</th>
                        <th scope="col">Reason</th>
                        <th scope="col" class="numeric">Quantity</th>
                        <th scope="col" class="numeric">Value (PHP)</th>
                        <th scope="col">Posted by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                    <tr wire:key="inventory-receipt-{{ $receipt->id }}">
                        <td class="receipt-date">{{ $receipt->effective_date->format('Y-m-d') }}</td>
                        <td>{{ $receipt->reference ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $receipt->reason_category)) }}</td>
                        <td class="numeric">{{ number_format((float) $receipt->quantity, 0) }} {{ $item->unit }}</td>
                        <td class="numeric">{{ $receipt->value_cents === null ? '—' : number_format($receipt->value_cents / 100, 2) }}</td>
                        <td>{{ $receipt->poster?->username ?? 'System migration' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="receipt-empty">No receipts recorded for this item.</td></tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </section>

    <section class="panel receipt-panel" aria-labelledby="inventory-receipt-files-heading">
        <header class="receipt-heading">
            <div>
                <h2 id="inventory-receipt-files-heading">Receipt files</h2>
                <p>Upload the actual receipt files for this item. Any number of files, up to 10&nbsp;MB each.</p>
            </div>
        </header>

        @can('inventory.update')
            <form class="inventory-form" wire:submit="uploadReceipts" style="margin-bottom:16px;">
                <div class="inventory-form-field">
                    <label for="receiptFiles">Add receipt files</label>
                    <input id="receiptFiles" type="file" multiple wire:model="receiptFiles"
                        wire:loading.attr="disabled" wire:target="receiptFiles">
                    @error('receiptFiles') <span class="error">{{ $message }}</span> @enderror
                    @error('receiptFiles.*') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div class="actions inventory-form-actions">
                    <button class="primary" type="submit" wire:loading.attr="disabled" wire:target="receiptFiles,uploadReceipts">
                        <span wire:loading.remove wire:target="uploadReceipts">Upload receipts</span>
                        <span wire:loading wire:target="uploadReceipts">Uploading…</span>
                    </button>
                </div>
            </form>
        @endcan

        <div class="receipt-table-scroll" role="region" aria-label="Receipt files" tabindex="0">
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
                    @forelse ($receiptAttachments as $attachment)
                    <tr wire:key="receipt-attachment-{{ $attachment->id }}">
                        <td>
                            <button type="button" class="receipt-thumb-button" wire:click="preview({{ $attachment->id }})"
                                title="Preview {{ $attachment->original_name }}" aria-label="Preview {{ $attachment->original_name }}">
                                @if ($attachment->isImage())
                                    <img class="receipt-thumb" src="{{ route('inventory.receipts.preview', $attachment) }}"
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
                            <button type="button" class="secondary" wire:click="preview({{ $attachment->id }})">View</button>
                            <a class="secondary" href="{{ route('inventory.receipts.download', $attachment) }}">Download</a>
                            @can('inventory.update')
                                <button type="button" class="secondary"
                                    wire:click="deleteReceipt({{ $attachment->id }})"
                                    wire:confirm="Delete this receipt file? This cannot be undone."
                                    wire:loading.attr="disabled">Delete</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="receipt-empty">No receipt files uploaded for this item.</td></tr>
                @endforelse
            </tbody>
            </table>
        </div>
    </section>

    @if ($previewAttachment)
        <div class="vat-modal-backdrop" role="presentation" wire:click.self="closePreview">
            <section class="vat-detail-modal receipt-preview-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-preview-heading">
                <header>
                    <h2 id="receipt-preview-heading">{{ $previewAttachment->original_name }}</h2>
                    <button class="vat-modal-close" type="button" wire:click="closePreview" aria-label="Close preview">&times;</button>
                </header>
                @if ($previewAttachment->isImage())
                    <img class="receipt-preview-image"
                        src="{{ route('inventory.receipts.preview', $previewAttachment) }}"
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
                    <a class="secondary" href="{{ route('inventory.receipts.download', $previewAttachment) }}">Download</a>
                </footer>
            </section>
        </div>
    @endif
</section>
