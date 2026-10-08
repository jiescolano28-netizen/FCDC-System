# 01: Inventory-only Dashboard

**What to build:** Update the authenticated Dashboard to show the persisted inventory position without presenting demonstration sales or unimplemented tax and accounting data as current business figures.

**Blocked by:** None (can start immediately).

**Status:** complete

- [x] The Dashboard shows the count of persisted inventory items and their total inventory value, calculated as quantity × unit cost; its existing inventory value-by-category chart remains based on persisted inventory.
- [x] Low-stock items are those with quantity greater than zero and at or below their reorder level. Out-of-stock items have quantity at or below zero.
- [x] The Dashboard shows recently added persisted inventory materials, newest first, and useful empty states when inventory or matching alert items are absent.
- [x] The Dashboard no longer shows demonstration sales charts, totals, or latest-sale details.
- [x] The Dashboard does not show VAT or accounting totals, or payable and VAT-filing alerts; low-stock alerts are the only alerts in scope.
- [x] Feature coverage and an authenticated browser smoke verify the inventory summaries, stock boundaries, removal of demo sales content, and empty states.
