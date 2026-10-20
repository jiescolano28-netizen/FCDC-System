# Feature spec: Inventory batch category attachments

## Status

**Implemented.** Mirrors the inventory receipt attachment feature
(`.scratch/inventory-receipt-attachments/spec.md`) for batch categories.

### Decisions locked
- **Shape:** multiple files per batch category, full receipt-style (upload, list, preview, download, delete).
- **Allowed file types:** any file type for now (no `mimes:` restriction).
- **Per-file size limit:** 10 MB (`max:10240` KB).
- **Storage disk:** private `local` disk at `inventory/batch-categories/...`; downloads go through an authorized route.
- **Upload/delete permission:** reuse `inventory.update`; list/download reuse `inventory.view`.
- **Table/model name:** `inventory_batch_category_attachments` / `InventoryBatchCategoryAttachment`.
- **Validation:** `StoreInventoryBatchCategoryAttachmentsRequest` is the single source of truth (shared `uploadRules()`).
- **Architecture:** `routes/web.php → InventoryBatchCategoryAttachmentController → App\Services\Inventory\… → model`, matching the receipt feature.

## Data model

### Table: `inventory_batch_category_attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `inventory_batch_category_id` | FK → `inventory_batch_categories.id` | `constrained()->cascadeOnDelete()` |
| `uploaded_by` | FK → `employees.id`, nullable | `nullOnDelete()`; indexed |
| `original_name` | string | Filename as uploaded |
| `path` | string | Storage path on the chosen disk |
| `disk` | string | Default `local` |
| `mime_type` | string, nullable | Content-detected where possible |
| `size_bytes` | unsigned bigint | From the uploaded file |
| `timestamps` | | |

### Model: `App\Models\InventoryBatchCategoryAttachment`
- `batchCategory()` → `belongsTo(InventoryBatchCategory::class)`; `uploader()` → `belongsTo(Employee::class, 'uploaded_by')`.
- `LogsActivity`, `isImage()`, and `human_size` accessor, mirroring `InventoryReceiptAttachment`.
- Relation on `InventoryBatchCategory`: `attachments(): HasMany(…)->latest()`.

## Backend

- **Form Request:** `StoreInventoryBatchCategoryAttachmentsRequest` (`attachmentFiles`, `attachmentFiles.*` → `file`, `max:10240`).
- **Services:** `BatchCategoryAttachmentFileStorage` (disk I/O), `StoreInventoryBatchCategoryAttachments` (transaction + rollback cleanup), `ListInventoryBatchCategoryAttachments`, `DeleteInventoryBatchCategoryAttachment`.
- **Controller:** thin `InventoryBatchCategoryAttachmentController` (`index`, `store`, `download`, `preview`, `destroy`).
- **Routes:** `inventory.batch-categories.attachments.index/store/download/preview/destroy`.

## UI

`BatchCategoryManagement` (Livewire, `resources/views/livewire/inventory/batch-category-management.blade.php`):
- Each active category row has an **Attachments** action opening a dialog scoped to that category.
- Dialog shows an upload form (multi-file), and a table (preview thumb, file, type, size, uploaded by, uploaded, actions).
- Image previews and a preview modal reuse the receipt styling (`.receipt-*`, `.vat-modal-backdrop`).
- `shell.js` opens/closes `#batch-category-attachments-modal` on the `batch-category-attachments-modal-open` / `-close` events and calls `closeAttachments` on close.

## Tests

`tests/Feature/InventoryBatchCategoryAttachmentTest.php` — multi-file upload, 10 MB rejection, permission boundary, JSON listing, authorized download/preview, delete removes row + file, and Livewire management (open, upload, preview, delete, validation).
