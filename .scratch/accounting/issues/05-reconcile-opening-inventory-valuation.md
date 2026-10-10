# 05: Approve opening quantities and inventory values

**What to build:** An accountant can reconcile approved item quantities and carrying values to opening Inventory and establish the real moving-average valuation baseline without duplicating existing stock.

**Blocked by:** 02: Review the cutover and opening accounting books.

**Status:** ready-for-agent

- [ ] Record approved per-item opening quantity, carrying value, cutover evidence and valuation approval; reconcile the item schedule exactly to the controlled opening Inventory balance.
- [ ] Match the approved opening position to the stock timeline at cutover; do not create another physical opening receipt or guess historical costs from the editable item unit cost.
- [ ] Reject unsupported quantity/value combinations, negative quantities/values, missing approval and a mismatched schedule; subsequent manual item-cost edits cannot alter the accounting baseline.
- [ ] Retain exact stored quantity/value precision and the chronological baseline required by later valued movements; display the approved opening cost and remaining value to authorized staff.
- [ ] Opening changes invalidate dependent readiness/approval until reviewed and must not rewrite posted history.
- [ ] Demonstrate a reconciled opening stock schedule and rejected mismatch; cover absence of duplicate quantity effects and preservation of the approved cost baseline.
