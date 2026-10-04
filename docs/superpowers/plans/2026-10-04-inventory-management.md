# Inventory Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate persisted inventory management into the Inventory Livewire module while retaining its routes and behavior, and preserve unset selling prices as unavailable for sale.

**Architecture:** Move the existing Livewire component and Blade view into `App\Livewire\Inventory` and `resources/views/livewire/inventory`; preserve `Inventory`, `InventoryController`, and every current URL/route name. Use the existing nullable `selling_price` migration and `availableForSale()` scope; do not invent a checkout flow, repurpose JSON endpoints, or backfill prices.

**Tech Stack:** Laravel, Livewire, Eloquent, Pest, Blade, browser smoke via Playwright.

**Spec:** `.scratch/livewire-module-pages/issues/02-inventory-management.md`; `.scratch/livewire-and-structure/issues/03-persisted-inventory.md`

## Global Constraints

- Preserve the existing management URL and named route, authentication boundary, and JSON CRUD paths and responses.
- Preserve persisted inventory behavior; session-only demonstrations must not mutate inventory or displayed real quantities.
- Selling price remains distinct from unit cost; legacy prices stay unset and unset prices are unavailable for sale.
- Use the shared application shell and current responsive appearance; remove superseded flat manager class and template.

---

### Task 1: Move the Livewire manager into Inventory

**Files:**
- Create: `app/Livewire/Inventory/InventoryManagement.php` (move and namespace existing class)
- Create: `resources/views/livewire/inventory/management.blade.php` (move existing template)
- Modify: `routes/web.php` (import new class; retain GET path, route name, and auth group)
- Modify: `resources/views/dashboard.blade.php` (keep persisted Inventory overview read-only; separate POS simulation from real inventory and use selling price)
- Modify: `resources/css/shell.css` only if selectors need adjustment; retain current appearance and responsive table behavior
- Delete: `app/Livewire/InventoryManagement.php`
- Delete: `resources/views/livewire/inventory-management.blade.php`

- [ ] Update existing behavior tests to reference the new namespaced component, add assertions for the named management route and the authenticated JSON index/store/update/delete behavior.
- [ ] Run `php artisan test tests/Feature/InventoryManagementTest.php` and confirm the moved-class/route or response assertions fail before the migration.
- [ ] Move component and view, update route import and view name, remove the superseded flat class/template and dashboard CRUD duplicate; retain the dashboard inventory listing as read-only with its manager link.
- [ ] Run the focused test file and `php artisan route:list --path=inventory`.
- [ ] Confirm unauthenticated manager/API requests redirect to login; confirm JSON calls remain JSON and route names/URLs are unchanged.

### Task 2: Prove selling-price and sale-eligibility invariants

**Files:**
- Modify: `tests/Feature/InventoryManagementTest.php`
- Inspect unchanged: `app/Models/Inventory.php`, `database/migrations/2026_10_04_000000_add_selling_price_to_inventories_table.php`, `app/Http/Controllers/InventoryController.php`

- [ ] Add a behavior test proving legacy inventory with nonzero cost has `selling_price === null` and is excluded by `Inventory::availableForSale()`, while a priced, positive-stock item is included.
- [ ] Run the focused test and confirm it fails if the sale eligibility scope admits unpriced stock or the nullable persistence contract is absent.
- [ ] Keep the production change minimal; current model scope and migration may already satisfy the invariant. Never copy `unit_cost` into `selling_price`.
- [ ] Exercise manager create/edit of optional selling price and ensure the value survives a fresh model read; retain existing search/category filtering, image upload, validation, stock status, delete, and JSON behavior tests.
- [ ] Run the focused test file and relevant existing tests.

### Task 3: Browser smoke and full verification

**Files:**
- No permanent files unless browser smoke exposes a real behavior regression; remove any temporary data/scripts afterward.

- [ ] Start the app using the repository's configured local run command and load `/inventory/manage` in a browser as an authenticated test employee with isolated inventory data.
- [ ] Create a priced inventory item with image; refresh and confirm its image and distinct cost/selling prices persisted.
- [ ] Edit the item, search by name, filter by category, and verify quantity/stock status and updated price.
- [ ] In the browser POS, confirm unpriced stock is absent, priced items display/use selling price, and demo checkout changes only simulated POS quantity—not the dashboard inventory listing, dashboard stock metrics, or persisted manager quantity; confirm checkout sends no inventory mutation request.
- [ ] Delete the created manager item and confirm it is absent after refresh; verify JSON inventory endpoints still return JSON and stay authenticated.
- [ ] Run the focused test file, full `php artisan test` suite once, and any repository type/build check configured for PHP/JS; report actual command output.
- [ ] Run code review on the final diff, resolve critical/important findings, and commit the verified implementation on the current branch.
