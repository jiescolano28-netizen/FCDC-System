# 01: Settings and shared application shell

**What to build:** An authenticated Settings page where company details can be edited temporarily for the signed-in demonstration session, inside the shared application shell used by migrated screens. This establishes usable navigation, styling, and session/logout conventions without adding business-record persistence.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Settings has its own authenticated named route and module-grouped Livewire page/template. Guests are redirected to login; existing Employee authentication remains unchanged.
- [ ] The shared shell preserves green-and-gold branding, signed-in identity, logout, sidebar collapse, dropdowns, active navigation, and narrow-screen usability. Existing Inventory management adopts this shell without changing its route or behavior. Links offered at this stage have working destinations.
- [ ] Company name, address, phone, tax-rate field, and currency selector use Livewire interactions. Edits survive navigation and refresh within the signed-in session; feedback identifies values as temporary, not persisted business settings. Preserve the existing illustrative team list without adding employee administration.
- [ ] Logout invalidates temporary demo state, and a subsequent employee cannot inherit another employee's values. Establish the same ownership/lifetime convention for later POS and journal demo state, without implementing placeholder APIs or empty features.
- [ ] Shared styles/assets are extracted without redesign; page-specific scripts do not run against absent elements. Homepage and login behavior stay unchanged.
- [ ] Exercise Settings edits, navigation, refresh, logout/relogin, and responsive shell behavior in the actual browser. Run affected existing behavioral checks; keep application changes on the migration integration branch.
