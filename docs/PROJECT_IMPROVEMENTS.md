# Project Improvement Opportunities

This list is based on a static review of the Laravel, Livewire, inventory/POS, routing, and test code. Findings describe the current repository; no implementation changes are included.

## Prioritized opportunities

### P1 — Unify inventory image storage and URL generation

**Finding:** Inventory images are written using incompatible paths. The Livewire manager stores files on the `public` disk under `inventory/...` (`app/Livewire/Inventory/InventoryManagement.php`, `save`). The JSON controller writes files to `public/uploads/inventory/...` and stores `uploads/inventory/...` in the database (`app/Http/Controllers/InventoryController.php`, `store`). The POS view builds image URLs as `asset('storage/'.$item->image)` (`resources/views/livewire/pos/point-of-sale.blade.php`). The configured `public` disk points to `storage/app/public` and its public link points from `public/storage` to that directory (`config/filesystems.php`). Consequently, an API-uploaded file is outside the storage link while its POS URL points inside it.

**Improvement:** Use one storage disk/path convention and one URL-generation approach for both interfaces. Account for existing records/files when changing the convention. Add a cross-interface regression check for upload and image display.

### P2 — Clean up images on inventory replacement and deletion

**Finding:** Livewire inventory replacement stores a new file without deleting the old file, and its `delete()` removes only the database record (`app/Livewire/Inventory/InventoryManagement.php`, `save` and `delete`). The JSON controller has explicit old-file cleanup during replacement and deletion (`app/Http/Controllers/InventoryController.php`, `update` and `destroy`). Existing image coverage verifies upload and preservation when editing without a replacement (`tests/Feature/InventoryManagementTest.php`), but not replacement or deletion cleanup.

**Improvement:** Apply consistent file lifecycle handling to both inventory interfaces. Test replacement, record deletion, and the persisted image's availability after cross-interface edits.

### P2 — Make the indicative POS VAT rate configurable

**Finding:** POS tax uses a hard-coded `0.12` in `app/Livewire/Pos/PointOfSale.php` (`taxFor`). The approved project design calls for a configurable indicative 12% VAT calculation (`docs/livewire-setup-and-structure.md`, “Application behavior”). VAT records and reporting fixtures are explicitly illustrative, but that does not provide configurability for the POS calculation.

**Improvement:** Read the demo rate from a documented configuration source and test a non-default rate. Retain the existing indicative/non-filing labels; this is not a recommendation to implement statutory VAT filing.

### P2 — Decide and document inventory authorization policy

**Finding:** Inventory management and the JSON inventory routes require authentication, but have no inventory-specific permission middleware (`routes/web.php`). Inventory Livewire actions such as `save`, `edit`, and `delete` do not perform permission checks (`app/Livewire/Inventory/InventoryManagement.php`). The existing `RolePermissionCatalog` defines employee and role permissions only (`app/Support/RolePermissionCatalog.php`). Therefore any authenticated employee can currently manage inventory. Whether that is a defect depends on intended staff access policy.

**Improvement:** Confirm whether inventory access is intentionally available to every authenticated employee. If it is restricted, define inventory permissions and enforce them consistently at routes and Livewire action boundaries; add denial tests for unauthorized users.

## Deliberate demonstration boundaries

Do not treat the following documented limits as accidental omissions within the current demonstration scope:

- **Accounting:** Production posting, payables/disbursements, ledgers, and financial calculations are deferred pending approved accounting rules and report definitions (`docs/livewire-setup-and-structure.md`, “Accounting boundary” and “Accounting demonstration pages”).
- **Tax:** VAT Records, VAT Summary and the Tax Report use recorded POS VAT; the report supports month, calendar quarter, inclusive custom dates, CSV export and browser printing. VAT Return Preparation remains illustrative/non-filing; no current tax screen provides complete statutory compliance functionality (`docs/livewire-setup-and-structure.md`, “VAT summary and POS tax report”; `GLOSSARY.md`).
- **POS and settings:** POS sales/stock and Settings are employee/session-scoped demonstration state; POS sales do not update persisted inventory or constitute recorded business sales (`docs/livewire-setup-and-structure.md`, “Implemented authenticated shell and Settings slice” and “Completed module-page cutover”).

Production-like use remains subject to the documented business-rule prerequisites; these findings do not change that boundary.
