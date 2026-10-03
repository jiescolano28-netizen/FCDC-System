# 03: Persisted Inventory Management and Selling Prices

**What to build:** Inventory managers can maintain persisted inventory records, find items quickly, and distinguish unit cost from a customer selling price; existing unpriced stock cannot be sold.

**Blocked by:** 02: Employee Authentication and Protected Application Shell.

**Status:** ready-for-agent

- [ ] Inventory listing shows identifying details, quantity, unit, unit cost, selling price, and stock status from persisted records.
- [ ] Authenticated users can create, edit, and delete inventory with the approved inventory fields, including selling price.
- [ ] Search and filters return matching persisted inventory.
- [ ] Existing inventory data is preserved by migration and selling price remains unset; unit cost is never copied into selling price.
- [ ] Inventory with no selling price is unavailable for checkout.
- [ ] Feature coverage verifies CRUD, search/filter behavior, preserved unset prices, and the unpriced checkout boundary.
