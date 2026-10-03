# 04: Paid Cash or Card Checkout

**What to build:** Cashiers can select saleable inventory, prepare a cart, and record an explicit paid cash or card sale that remains available after reload and reduces stock consistently.

**Blocked by:** 03: Persisted Inventory Management and Selling Prices.

**Status:** ready-for-agent

- [ ] Cashiers can search saleable inventory and build a cart with quantities.
- [ ] Checkout rejects items without a selling price and quantities exceeding available stock.
- [ ] The cart displays subtotal, clearly indicative VAT, and total using the configured rate.
- [ ] Cash or card is recorded as the tender for a paid sale.
- [ ] Persisted sale details retain item, quantity, checkout-time price, tender, date, tax amount, and total.
- [ ] Sale persistence and stock deduction are one consistent operation; any validation or persistence failure leaves both sale records and stock unchanged.
- [ ] Feature coverage verifies cash and card sales, persisted details, stock reduction, unavailable-stock rejection, and rollback invariants.
