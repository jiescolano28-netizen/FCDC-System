# 03: Inventory Overview

**What to build:** A separately routed Inventory overview showing actual persisted materials and their quantities, prices, values, and stock status, with the existing search/filter and management interactions available outside the monolith.

**Blocked by:** 02: Inventory Management module migration.

**Status:** backend-verified; POS-isolation and browser-smoke-pending

- [x] The overview has its own authenticated named route, Inventory-grouped Livewire page/template, and shared shell. It does not collide with the existing Inventory JSON endpoint or replace the separate management page.
- [x] Livewire search and category filtering select persisted items; counts, quantity, cost, selling price, value, stock status, and last-updated information render from database records.
- [x] Preserve the current Manage Inventory link and working edit/delete interactions, reusing the migrated manager's existing behavior rather than establishing a competing CRUD implementation. Persisted changes are visible in both Inventory screens after navigation/refresh.
- [ ] A demo POS sale leaves this page's real quantities unchanged. Empty/filtered states are usable, and the current visual/responsive presentation is retained. Empty and filtered states are covered by feature tests; POS isolation and browser-level visual/responsive behavior remain unverified.
- [x] The overview is read-only apart from search/filter; CRUD stays in the migrated manager, with no competing overview-local interactions or legacy route/component shims.
- [ ] Exercise direct entry, guest protection, searching/filtering, editing/deletion, and switching between overview and management in the actual browser with isolated data. Run affected existing behavior checks.

**Backend verification (2026-10-04):** `php artisan test --filter=InventoryManagementTest` passes (12 tests, 84 assertions), covering overview authentication, persisted calculations, search/category filtering, empty state, manager-to-overview updates and deletion, navigation, and existing manager/JSON behavior. POS isolation and the required actual-browser smoke remain unverified.
