# 01: Settings and shared application shell

**What to build:** Backend support for authenticated, temporary Settings demo state. Values belong to the signed-in Employee's session, survive requests in that session, and are cleared on logout; no business settings are persisted. Settings presentation and the shared application shell are deferred.

**Blocked by:** None (can start immediately).

**Status:** backend-verified; frontend-deferred

- [x] Settings has its own authenticated named route and module-grouped Livewire page/component. Guests are redirected to login; existing Employee authentication remains unchanged.
- [x] Company details use validated Livewire-backed state scoped to the signed-in session; updates survive navigation and refresh in that session, with no persisted business settings.
- [x] Logout invalidates temporary settings, and a later employee cannot inherit them. The per-employee session key establishes the ownership/lifetime convention for future POS and journal demo state without placeholder APIs.
- [x] Existing Inventory management route and backend response contracts remain covered. The JSON contract test supplies the matching session CSRF token and header.
- [x] Affected backend checks pass: Inventory, Settings, employee authentication, and homepage feature tests (24 tests, 179 assertions).
- [ ] **Deferred frontend:** shared shell branding, navigation, collapse/dropdowns, active states, responsive behavior, Settings page styling, extracted frontend assets/scripts, and actual-browser flow. Homepage/login UI behavior is also deferred for frontend verification.
- [ ] **Integration:** confirm changes are on the migration integration branch before integration. Current branch at verification: `dev/jp`.

**Backend verification (2026-10-04):** The targeted inventory JSON regression test passes (12 assertions), and the affected feature suite passes (24 tests, 179 assertions). Frontend browser checks are deferred per the current scope. The required migration integration branch is not confirmed.
