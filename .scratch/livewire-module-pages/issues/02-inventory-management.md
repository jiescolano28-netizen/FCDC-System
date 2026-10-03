# 02: Inventory Management module migration

**What to build:** Keep the existing working Inventory management page, but place it in the Inventory module and shared application shell so future Inventory work has one existing management implementation to reuse.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] The existing management URL and named route still open the authenticated manager; its Livewire class/template are grouped under Inventory. Migrate all affected references and remove the superseded flat component/template rather than leaving aliases.
- [ ] Creation, editing, deletion, validation, search, category filtering, image handling, stock status, and distinct selling-price behavior retain their existing working outcomes. Legacy unpriced items remain unpriced; do not backfill prices from cost.
- [ ] Inventory mutations remain persisted business operations. Session-only POS demonstrations must not become Inventory writes or change displayed real quantities.
- [ ] Existing inventory JSON CRUD paths and responses remain intact, including their authentication boundary; do not repurpose a JSON URL as an HTML page.
- [ ] Use the shared shell with current appearance and responsive behavior; do not duplicate management functionality or retain an unused Inventory-only layout after its consumers migrate.
- [ ] Smoke the real manager in the browser with isolated data: create/edit an item, search/filter it, verify image and price behavior after refresh, then delete it. Update affected existing behavior tests and exercise their scenarios.
