# 06: Dashboard-owned Reports

**What to build:** Preserve the existing Reports screen as a standalone authenticated page owned by Dashboard, with its cards, charts, and recent demonstration sales table.

**Blocked by:** 04: POS.

**Status:** ready-for-agent

- [ ] Reports has its own named route and Dashboard-grouped Livewire page/template using the shared shell, with a working navigation entry. It is not a seventh module or hidden Dashboard section.
- [ ] Preserve the existing report cards, sales-trend presentation, inventory category chart, and recent-sales table. Sales fixtures and completed session demo sales are clearly identified as illustrative rather than recorded business activity.
- [ ] A session demo checkout is available in recent sales after navigating to Reports and refreshing. Keep illustrative fixed chart series distinct; do not add a persisted sales aggregation pipeline.
- [ ] Inventory quantities and category values use real persisted Inventory records and ignore simulated POS stock deductions. Preserve current appearance and usable empty states.
- [ ] Charts use page-scoped JavaScript and update without requiring the monolith or unrelated canvases. Page interactions use Livewire where applicable.
- [ ] Smoke a POS checkout followed by Reports navigation/refresh, charts, direct entry, and guest protection in the actual browser; verify real inventory values remain unchanged by the demo sale.
