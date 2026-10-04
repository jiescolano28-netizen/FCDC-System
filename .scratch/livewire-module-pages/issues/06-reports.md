# 06: Dashboard-owned Reports

**What to build:** Preserve the existing Reports screen as a standalone authenticated page owned by Dashboard, with its cards, charts, and recent demonstration sales table.

**Blocked by:** 04: POS.

**Status:** in-progress: actual-browser Livewire actions return HTTP 419

- [x] Reports has its own named route and Dashboard-grouped Livewire page/template using the shared shell, with a working navigation entry. It is not a seventh module or hidden Dashboard section.
- [x] Preserve the existing report cards, sales-trend presentation, inventory category chart, and recent-sales table. Sales fixtures and completed session demo sales are clearly identified as illustrative rather than recorded business activity.
- [x] A session demo checkout is available in recent sales after navigating to Reports and refreshing. Keep illustrative fixed chart series distinct; do not add a persisted sales aggregation pipeline.
- [x] Inventory quantities and category values use real persisted Inventory records and ignore simulated POS stock deductions. Preserve current appearance and usable empty states.
- [ ] Charts use page-scoped JavaScript and update without requiring the monolith or unrelated canvases. Page interactions use Livewire where applicable.
- [ ] Smoke a POS checkout followed by Reports navigation/refresh, charts, direct entry, and guest protection in the actual browser; verify real inventory values remain unchanged by the demo sale.

> Backend coverage passes, including a POS checkout followed by a Reports request and the persisted-stock invariant. Browser direct entry and guest protection were observed, but POS and chart Livewire updates return HTTP 419 in the local browser, so interactive chart rendering and the complete POS-to-Reports browser flow remain unverified.
