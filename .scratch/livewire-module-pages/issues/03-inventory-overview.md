# 03: Inventory Overview

**What to build:** A separately routed Inventory overview showing actual persisted materials and their quantities, prices, values, and stock status, with the existing search/filter and management interactions available outside the monolith.

**Blocked by:** 02: Inventory Management module migration.

**Status:** ready-for-agent

- [ ] The overview has its own authenticated named route, Inventory-grouped Livewire page/template, and shared shell. It does not collide with the existing Inventory JSON endpoint or replace the separate management page.
- [ ] Livewire search and category filtering select the expected persisted items; counts, quantity, cost, selling price, value, stock status, and existing edited-item information reflect the records rather than fixtures.
- [ ] Preserve the current Manage Inventory link and working edit/delete interactions, reusing the migrated manager's existing behavior rather than establishing a competing CRUD implementation. Persisted changes are visible in both Inventory screens after navigation/refresh.
- [ ] A demo POS sale leaves this page's real quantities unchanged. Empty/filtered states are usable, and the current visual/responsive presentation is retained.
- [ ] Remove superseded overview-local interactions as consumers move, without introducing permanent legacy route or component shims.
- [ ] Exercise direct entry, guest protection, searching/filtering, editing/deletion, and switching between overview and management in the actual browser with isolated data. Run affected existing behavior checks.
