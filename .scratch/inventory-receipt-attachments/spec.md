# Feature spec: Inventory receipt attachments

## Status

**Planning mode (active).** This document is the shared plan agreed before full implementation. Keep it updated as decisions are made.

### Decisions locked so far
- **Allowed file types:** any file type for now (no `mimes:` restriction). Type policy to be decided later.
- **Per-file size limit:** 10 MB (`max:10240` KB).
- **Storage disk:** private `local` disk (not publicly reachable); downloads go through an authorized route.
- **Upload/delete permission:** reuse `inventory.update`.
- **Table/model name:** `inventory_receipt_attachments` / `InventoryReceiptAttachment`.
- **Validation:** handled in a Laravel **Form Request** (`StoreInventoryReceiptAttachmentsRequest`) — the single place for file-type/size rules.
- **Architecture:** `routes/web.php → InventoryReceiptAttachmentController → App\Services\Inventory\… → model`. Controllers stay thin; business logic lives in services. Dedicated controller (not `InventoryController`).

## Progress (keep this current)

Legend: ✅ done · 🔲 not started · ⏸️ paused

| Item | State |
|---|---|
| Plan/spec written | ✅ |
| Decisions recorded | ✅ |
| Model `InventoryReceiptAttachment` | ✅ done |
| Migration `create_inventory_receipt_attachments_table` | ✅ done (**run `php artisan migrate`** — not yet applied locally; MySQL was offline) |
| `Inventory::receiptAttachments()` relation | ✅ done |
| Form Request `StoreInventoryReceiptAttachmentsRequest` | ✅ done (introduces `app/Http/Requests/`) |
| Controller `InventoryReceiptAttachmentController` (thin) | ✅ done |
| Services (`Store…`, `Delete…`, `List…`, `ReceiptFileStorage`) | ✅ done |
| Dedicated routes in `routes/web.php` | ✅ done |
| Authorized download route | ✅ done |
| Inline preview route + thumbnails + preview modal | ✅ done |
| Profile page UI (upload + list) | ✅ done |
| Tests | ✅ done — `tests/Feature/InventoryReceiptAttachmentTest.php` (6 passing) |
| `php.ini` limits documented/handled | 🔲 still open |

> Note: implemented and covered by `tests/Feature/InventoryReceiptAttachmentTest.php`. The migration has **not** been applied to the local MySQL database yet (the server was offline); run `php artisan migrate` when the database is available.

## Goal

Let a user attach an **unlimited number of receipt files** to a single inventory item, and list all uploaded receipts back on the inventory profile page. Each inventory item "has many" receipts; the only upload constraints are a per-file **size limit** (10 MB) and the file-type policy (any type for now, decided later).

## Scope

### In scope
- New table + model storing receipt files linked to an inventory item.
- One-to-many relation between inventory items and receipt attachments.
- Upload (multiple files in one action, no count cap) from the inventory UI.
- Listing uploaded receipts on the inventory profile page.
- Download/view a receipt (authorized, since files live on the private disk).
- Delete a receipt.
- Per-file size validation (10 MB) and a server-side safety net (PHP upload limits).

### Out of scope (for now)
- Editing/replacing an uploaded receipt (delete + re-upload instead).
- Receipt previews/thumbnails or OCR.
- Linking receipts to POS transactions, suppliers, or accounting entries.
- Any migration of the existing single `inventories.image` column (that stays as-is).

## Naming decision

Chosen table/model name: **`inventory_receipt_attachments`** / `InventoryReceiptAttachment`.

Rationale:
- Files are always receipts (not a generic file bag), so the name is precise.
- Matches the repo's descriptive plural table style (e.g. `supplier_purchase_invoices`, `recovered_material_assessments`).
- Leaves room for other attachment types later without renaming.

> Note: the inventory profile already surfaces a `receipts` variable that is actually **stock movements** (`purchase_receipt` / `stock_in`). The plan should keep these conceptually distinct: stock-movement receipts (transactions) vs. uploaded receipt files (documents). UI copy should make this clear.

## Data model

### Table: `inventory_receipt_attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `inventory_id` | FK → `inventories.id` | `constrained()->cascadeOnDelete()`; indexed by the FK |
| `uploaded_by` | FK → `employees.id`, nullable | `nullOnDelete()`; indexed |
| `original_name` | string | Filename as uploaded by the user |
| `path` | string | Storage path on the chosen disk |
| `disk` | string | Default `local` (private disk) |
| `mime_type` | string, nullable | From the uploaded file |
| `size_bytes` | unsigned bigint | From the uploaded file |
| `timestamps` | | |

### Model: `App\Models\InventoryReceiptAttachment`
- `$fillable`: `inventory_id`, `uploaded_by`, `original_name`, `path`, `disk`, `mime_type`, `size_bytes`.
- `$casts`: `size_bytes` => integer.
- Relations: `inventory()` → `belongsTo(Inventory::class)`; `uploader()` → `belongsTo(Employee::class, 'uploaded_by')`.
- `LogsActivity` enabled (logs `inventory_id`, `original_name`, `mime_type`, `size_bytes`), matching `Inventory`.
- `deleting` hook deletes the stored file when the row is removed.
- `human_size` accessor for display (e.g. "1.4 MB").

### Relation on `Inventory`
```php
public function receiptAttachments(): HasMany
{
    return $this->hasMany(InventoryReceiptAttachment::class)->latest();
}
```

## Upload & validation flow

### Validation lives in a Form Request (standard Laravel)
Create a dedicated Form Request so the file-type/size policy has **one source of truth** and can be changed later without hunting through components:

```bash
php artisan make:request StoreInventoryReceiptAttachmentsRequest
```

```php
// app/Http/Requests/StoreInventoryReceiptAttachmentsRequest.php
public function authorize(): bool
{
    return auth()->user()?->can('inventory.update') ?? false;
}

public function rules(): array
{
    return [
        'receiptFiles'   => ['required', 'array'],
        'receiptFiles.*' => ['file', 'max:10240'], // 10 MB per file; add mimes: later when policy is known
    ];
}
```

**Design note — validation flow.** With the dedicated controller, the Form Request is used the standard Laravel way: the controller method type-hints `StoreInventoryReceiptAttachmentsRequest`, which authorizes and validates before the action runs. To keep a single source of truth, expose the rules as `public static function rules(): array` so any Livewire component that lists/uploads receipts can reuse the exact same rules (see "Architecture & code structure").

The request class remains **the place where the file type (`mimes`) and size (`max`) rules are handled** — no `mimes:` yet, so any file type is allowed until the policy is decided.

- **No array `max:`** — unlimited files per submission.
- **No `mimes:`** — any file type is accepted for now (policy to be decided later).
- Store each file via `->store('inventory/receipts', 'local')` (private disk).
- Persist one `InventoryReceiptAttachment` row per uploaded file, recording `original_name`, `path`, `disk`, `mime_type`, `size_bytes`, `uploaded_by`.
- Wrap uploads in a DB transaction; clean up any partially stored files on failure.

### Server-side safety net
- PHP `upload_max_filesize` and `post_max_size` (`php.ini`) still cap the total request. Many large files can fail even when app validation passes — document the required values and handle the resulting 413/empty-request case gracefully.
- Consider a friendly error when `post_max_size` is exceeded (Laravel sees an empty request in that case).

## Architecture & code structure (layering standard)

Follow the repo's existing convention: **route → controller → service → model**, with business logic in services. Do **not** pile receipt logic into `InventoryController` — this feature gets its own controller and its own service(s).

### Request flow

```
routes/web.php
   └─ StoreInventoryReceiptAttachmentsRequest    (authorize + validate: file type & size)   [store only]
        └─ InventoryReceiptAttachmentController  (thin: call service, return response)
             └─ App\Services\Inventory\…          (business logic, DB::transaction, ValidationException)
                  └─ App\Models\InventoryReceiptAttachment / Inventory
```

> This feature introduces the `app/Http/Requests/` directory — the repo has no Form Requests yet. `php artisan make:request StoreInventoryReceiptAttachmentsRequest` will create it.

### Rules
- **Controllers stay thin.** No business logic, no file I/O, no multi-step domain rules. They validate (via Form Request), delegate to a service, and shape the HTTP response.
- **Services own the business logic.** One class per action, `handle(...)` method, wrapped in `DB::transaction`, throwing `ValidationException`/domain exceptions — exactly like `App\Services\Inventory\RecordStockIn`.
- **One controller per concern.** Receipt attachments get a dedicated controller, not extra methods on `InventoryController`. This keeps each controller small and obvious to locate.
- **File I/O isolation.** Physical store/delete of files lives in a small dedicated service so the domain service doesn't mix storage concerns with persistence.

### Proposed classes & files

| Layer | Class | Purpose |
|---|---|---|
| Request | `App\Http\Requests\StoreInventoryReceiptAttachmentsRequest` | File-type + size rules (single source of truth). Client input validation. |
| Controller | `App\Http\Controllers\InventoryReceiptAttachmentController` | `index`, `store`, `download`, `destroy` — thin, delegates to services. |
| Service | `App\Services\Inventory\StoreInventoryReceiptAttachments` | Validate inventory state, persist one row per file, orchestrate storage. |
| Service | `App\Services\Inventory\DeleteInventoryReceiptAttachment` | Authorization/ownership check + delete row + delete stored file. |
| Service | `App\Services\Inventory\ListInventoryReceiptAttachments` | Fetch attachments for an inventory item (keeps controller query-free). |
| Service | `App\Services\Inventory\ReceiptFileStorage` | Low-level `put`/`delete` on the `local` disk (isolates file I/O). |
| Model | `App\Models\InventoryReceiptAttachment` | Persistence + relations + activity log. |

> Services are the shared unit of logic: the HTTP controller uses them for the API surface, and — if any upload UI stays inside Livewire — the Livewire component calls **the same services**, so logic never duplicates across controllers and components.

### Routes (dedicated, separate from `InventoryController`)

No `routes/api.php` is wired up, so define these as named web routes in `routes/web.php`, matching the existing `/inventory` JSON endpoints:

```php
Route::get('/inventory/{inventory}/receipts', [InventoryReceiptAttachmentController::class, 'index'])
    ->middleware('permission:inventory.view')->name('inventory.receipts.index');

Route::post('/inventory/{inventory}/receipts', [InventoryReceiptAttachmentController::class, 'store'])
    ->middleware('permission:inventory.update')->name('inventory.receipts.store');

Route::get('/inventory/receipts/{attachment}/download', [InventoryReceiptAttachmentController::class, 'download'])
    ->middleware('permission:inventory.view')->name('inventory.receipts.download');

Route::get('/inventory/receipts/{attachment}/preview', [InventoryReceiptAttachmentController::class, 'preview'])
    ->middleware('permission:inventory.view')->name('inventory.receipts.preview');

Route::delete('/inventory/receipts/{attachment}', [InventoryReceiptAttachmentController::class, 'destroy'])
    ->middleware('permission:inventory.update')->name('inventory.receipts.destroy');
```

- The upload endpoint (`store`) is the dedicated file-upload API for receipts.
- Downloads are always authorized (private disk), never direct file URLs.
- Preview (`preview`) serves files inline (authorized) so image thumbnails and the modal can render without exposing the private disk.

## UI plan

Located on the inventory profile page (`app/Livewire/Inventory/InventoryProfile.php` + `resources/views/livewire/inventory/inventory-profile.blade.php`):

1. **Receipt attachments section** (distinct from the existing stock-movement "receipts" table).
2. **Thumbnails** — each row shows a small image for image files (loaded from the authorized `preview` route, `loading="lazy"`), or an uppercase file-extension badge for non-images.
3. **Upload control** — multi-file input (`multiple`), submit button, per-file progress/state. Posts to `inventory.receipts.store`, or calls the shared services if a Livewire action is used.
4. **Preview modal** — clicking a thumbnail or "View" opens a modal (reusing the `.vat-modal-backdrop` pattern) that shows the image inline; non-images show a "preview unavailable" note plus a Download link.
5. **List** — filename, type, size, uploaded by, uploaded date; View / Download / Delete actions.
6. **Empty state** — message when no receipts are attached yet.
7. Validation error display for oversize files (and wrong-type once the policy is set).

## Permissions

Reuse existing inventory permissions from Spatie:
- Upload / delete: `inventory.update` (**decided** — reuses the existing permission, no new permission).
- View / download: `inventory.view`.

## Storage & downloads

- Files are stored on the private `local` disk at `inventory/receipts/...`.
- Because the disk is private, downloads must go through an **authorized route** that checks `inventory.view` and streams the file (e.g. `Storage::disk('local')->download($attachment->path, $attachment->original_name)`). No direct public URL.
- Attachments must not be exposed via a guessable public path.

## Activity logging

`LogsActivity` is enabled on the model, logging `inventory_id`, `original_name`, `mime_type`, `size_bytes` for `created`/`deleted` receipt attachments (who attached/removed what), consistent with `Inventory`.

## Phased implementation plan

**Phase 1 — Data layer**
1. `php artisan make:model InventoryReceiptAttachment -m`
2. Write the migration (table per data model above).
3. Add `Inventory::receiptAttachments()` and the inverse relations.
4. Run migration; confirm rollback works.

**Phase 2 — Backend (route → controller → service)**
5. `php artisan make:request StoreInventoryReceiptAttachmentsRequest` (validation source of truth: type + size).
6. `php artisan make:controller InventoryReceiptAttachmentController` — thin: `index`, `store`, `download`, `destroy`.
7. Services: `StoreInventoryReceiptAttachments`, `DeleteInventoryReceiptAttachment`, `ListInventoryReceiptAttachments`, `ReceiptFileStorage`.
8. Register the dedicated routes in `routes/web.php` (separate from `InventoryController`).
9. Implement download streaming via the authorized route respecting `inventory.view`.

**Phase 3 — UI**
10. Build the receipts section on the inventory profile page (upload, list, empty state, errors); post uploads to the receipt routes / call the services.
11. Wire up View/Download and Delete, with loading states.

**Phase 4 — Hardening**
12. Tests (Pest feature tests): upload multiple files, size-limit rejection, relation integrity, delete removes file, permission boundaries, thin-controller/service behavior.
13. Decide/document `php.ini` limits; add user-facing handling for oversized requests.
14. Optional: activity-log assertions; `vendor/bin/pint`.

## Decisions (answered)

1. **Per-file size limit** — 10 MB (`max:10240`). ✅
2. **Allowed file types** — any file type for now; policy to be decided later (no `mimes:` rule). ✅
3. **Storage disk** — private `local` disk with an authorized download route. ✅
4. **Delete permission** — reuse `inventory.update`. ✅

## Open questions (still to decide)

1. **Download route shape** — resolved in favor of controller streaming (implemented).
2. **Type policy** — will likely be finalized once the real requirements are known.
3. **Orphan cleanup** — scheduled job / command to remove stored files with no matching row?
4. **`php.ini` limits** — document/raise `upload_max_filesize` and `post_max_size` and add friendly handling when they are exceeded.

## Testing notes

- Feature tests use in-memory SQLite (`phpunit.xml`), but file uploads should use `Storage::fake('local')`.
- Cover: multiple-file upload, per-file size rejection (10 MB), relation integrity, `cascadeOnDelete` relation behavior, delete removes the stored file, and auth/permission boundaries (`inventory.update` / `inventory.view`).
- Since any file type is allowed for now, do **not** assert MIME rejection; add those once the type policy is decided.

## Comments

- **2026-10-14:** Plan consolidated in planning mode. Decisions locked: any file type (no `mimes:` for now), 10 MB per file, private `local` disk with authorized downloads, reuse `inventory.update`. Added Form Request (`StoreInventoryReceiptAttachmentsRequest`) as the single source of truth for file-type/size validation. Noted Livewire/Form Request integration choice (Options A/B) to resolve.
- **2026-10-14:** Architecture direction set: follow the repo standard `routes/web.php → controller → service → model`. Dedicated `InventoryReceiptAttachmentController` (thin, no business logic) and dedicated `App\Services\Inventory\…` services (business logic in `handle()`, `DB::transaction`). Dedicated receipt routes added (separate from `InventoryController`). Resolved the earlier Options A/B in favor of the controller path; UI integration question remains.
- **2026-10-14 (implemented):** Built the full feature.
    - **Model:** `InventoryReceiptAttachment` (+`uploader()` relation, `human_size` accessor, activity log). File deletion moved into the service (removed the model `deleting` hook) to keep the single responsibility in the service layer.
    - **Request:** `StoreInventoryReceiptAttachmentsRequest` with shared `uploadRules()` (10 MB, any type).
    - **Services:** `ReceiptFileStorage` (disk I/O), `StoreInventoryReceiptAttachments` (transaction + rollback cleanup), `DeleteInventoryReceiptAttachment`, `ListInventoryReceiptAttachments`.
    - **Controller:** thin `InventoryReceiptAttachmentController` (`index`, `store`, `download`, `destroy`).
    - **Routes:** `inventory.receipts.index/store/download/destroy` (verified via `route:list`).
    - **UI:** dedicated "Receipt files" panel on the inventory profile; the Livewire `InventoryProfile` calls the shared services (mirrors how `InventoryManagement` calls services). Downloads use the authorized route.
    - **Tests:** `tests/Feature/InventoryReceiptAttachmentTest.php` — 6 passing (multi-file upload of mixed types, 10 MB rejection, permission boundary, JSON listing, authorized download, delete removes row + file).
    - **Note:** 4 unrelated accounting/stock tests (`ChartOfAccountsTest`, `FiscalYearClosingTest`, `ProductionAccountingActivationTest`, `ValuedStockMovementTest`) fail both with and without these changes — verified pre-existing.
    - **Pending:** run `php artisan migrate` against the app database; document `php.ini` upload limits.
- **2026-10-14 (UI preview):** Added the requested preview UI.
    - New authorized inline route `inventory.receipts.preview` (`InventoryReceiptAttachmentController@preview`) so private-disk images can render inline.
    - Table now shows a **thumbnail** per row (image thumbnail via the preview route, or a file-extension badge for non-images) plus a "View" action.
    - Clicking a thumbnail/View opens a **preview modal** (reusing the `.vat-modal-backdrop` / `.vat-detail-modal` pattern) showing the image inline; non-images show a "preview unavailable" note with a Download link.
    - `InventoryProfile` gained `previewAttachmentId`, `preview()`, `closePreview()`.
    - Added `.receipt-thumb`, `.receipt-file-type`, `.receipt-preview-image` styles in `resources/css/shell.css`; rebuilt assets with `npm run build`.
    - Tests: now **8 passing**, including inline preview and thumbnail/modal behavior.
- **2026-10-14 (bug fix — images not previewing):** Reported: uploaded images could not be viewed.
    - **Root cause:** uploaded mime was stored from `UploadedFile::getClientMimeType()`. Livewire's `TemporaryUploadedFile` never receives a client mime type (its parent constructor passes no `type`), so it reports `application/octet-stream` for every file uploaded through the UI. `isImage()` was therefore false → no thumbnail and a "preview unavailable" modal. Tests missed it because they uploaded through the HTTP controller with an explicit client mime.
    - **Fix:** `ReceiptFileStorage::store()` now records content-detected `getMimeType()` (which `TemporaryUploadedFile` overrides to sniff file contents), falling back to the client mime only when detection is generic.
    - **Back-compat:** `InventoryReceiptAttachment::isImage()` also falls back to the filename extension, and `preview()` sniffs the content type for rows still stored as `application/octet-stream`, so already-uploaded images preview without re-uploading.
    - Tests: now **10 passing**, adding a Livewire upload-path test (reproduces the bug) and a legacy octet-stream row test.
