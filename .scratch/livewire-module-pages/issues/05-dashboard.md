# 05: Dashboard

**What to build:** Keep Dashboard at its existing route, now as a Dashboard-grouped Livewire page containing only its overview, charts, and alerts rather than every application screen.

**Blocked by:** 04: POS.

**Status:** ready-for-agent

- [ ] The existing Dashboard URL and named route resolve to an authenticated, module-grouped Livewire page using the shared shell. Login's existing Dashboard destination remains valid.
- [ ] Week/month/year selection and sales-chart updates work through Livewire with scoped chart JavaScript. Preserve existing illustrative period series, clearly labeled as such; do not invent a persisted reporting pipeline or silently equate fixed chart fixtures with session sales.
- [ ] Inventory count/value, category values, and low-stock alerts come from persisted Inventory records. Demo POS checkout does not change these real inventory metrics.
- [ ] Latest-sale information can show the completed session demo sale, with demonstration status distinct from real inventory metrics. Preserve usable empty states and existing appearance/responsiveness.
- [ ] Charts initialize/update only for this page, without duplicate instances or scripts expecting other modules' elements. Other module content is not rendered in the Dashboard page.
- [ ] Smoke direct entry, period changes, navigating away/back, a preceding POS demo checkout, and refresh in the actual browser. Confirm real stock metrics remain unchanged by that checkout and guest protection remains intact.
