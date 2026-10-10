# 12: Post paid POS sales and frozen COGS atomically

**What to build:** Each completed paid sale updates stock and the books once, using recorded sale/VAT amounts and moving-average COGS, with card proceeds kept out of Bank until settlement.

**Blocked by:** 06: Post valued non-sale stock movements.

**Status:** implemented

- [x] Use the sale saved VAT-exclusive subtotal, output VAT and total rather than recomputing historical tax; debit Cash, confirmed-transfer Bank or gross Card Settlement Receivable/Clearing as appropriate.
- [x] Credit Sales and Output VAT and post a separate frozen COGS debit/Inventory credit from the valued stock cost; later cost edits never rewrite the original sale cost.
- [x] Sale, item snapshots, stock quantity/value, existing linked VAT record and exactly-once accounting effects succeed or fail together; missing mappings or failed posting prevent completion.
- [x] Preserve cash/card/bank-transfer payment validation and existing transaction identifiers; introduce neither credit POS nor refunds/cancellations nor processor integration.
- [x] Checkout uses its source-action permission, not accounting-page access; enforce active accounts, cutover/date/closed-period and stock/valuation rules on the server.
- [x] Demonstrate PHP 180 Sales plus PHP 21.60 VAT producing PHP 201.60 Cash or card clearing, with the correct separate COGS/Inventory effect.
- [x] Cover insufficient stock, missing mapping, duplicate completion, frozen cost and transaction rollback; later card settlement/fees remain authorized manual journals.

**Verification:** Focused POS accounting, checkout and reporting tests pass. Coverage includes the PHP 180 + PHP 21.60 example, cash/card/bank routing, moving-average COGS, exact extended COGS cents when rounded unit cost differs, mapping and closed-period rejection, duplicate posting protection, insufficient stock, saved unit-cost snapshot, and rollback scenarios.
