# 12: Post paid POS sales and frozen COGS atomically

**What to build:** Each completed paid sale updates stock and the books once, using recorded sale/VAT amounts and moving-average COGS, with card proceeds kept out of Bank until settlement.

**Blocked by:** 06: Post valued non-sale stock movements.

**Status:** ready-for-agent

- [ ] Use the sale saved VAT-exclusive subtotal, output VAT and total rather than recomputing historical tax; debit Cash, confirmed-transfer Bank or gross Card Settlement Receivable/Clearing as appropriate.
- [ ] Credit Sales and Output VAT and post a separate frozen COGS debit/Inventory credit from the valued stock cost; later cost edits never rewrite the original sale cost.
- [ ] Sale, item snapshots, stock quantity/value, existing linked VAT record and exactly-once accounting effects succeed or fail together; missing mappings or failed posting prevent completion.
- [ ] Preserve cash/card/bank-transfer payment validation and existing transaction identifiers; introduce neither credit POS nor refunds/cancellations nor processor integration.
- [ ] Checkout uses its source-action permission, not accounting-page access; enforce active accounts, cutover/date/closed-period and stock/valuation rules on the server.
- [ ] Demonstrate PHP 180 Sales plus PHP 21.60 VAT producing PHP 201.60 Cash or card clearing, with the correct separate COGS/Inventory effect.
- [ ] Cover insufficient stock, missing mapping, duplicate completion, frozen cost and transaction rollback; later card settlement/fees remain authorized manual journals.
