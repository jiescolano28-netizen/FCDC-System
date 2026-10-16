# 08: Preserve inventory valuation and posting integrity

**What to build:** Inventory evidence, stock adjustments, and valued stock movements retain their existing accounting meaning and atomicity. Unresolved rework and inventory-loss proposals cannot alter stock value or route to Miscellaneous Expense.

**Blocked by:** 02: Seed the complete proposed chart safely; 03: Preserve safe account maintenance and approval; 04: Enforce special-account accounting treatment; 05: Block Miscellaneous Expense until its policy is approved

**Status:** implementation-complete

- [x] Uploading a receipt through the authorized surface leaves inventory carrying value and journal effects unchanged.
- [x] Quantity adjustments remain distinct from approved shrinkage; stock differences are not automatically classified as losses or routed to 6190.
- [x] Valued stock movements and corrections keep quantity, carrying value, and GL effects atomic and preserve forward-only moving weighted-average valuation.
- [x] Account 5030 remains unavailable pending rework policy; its presence does not change capitalization, expense recognition, or inventory valuation.
