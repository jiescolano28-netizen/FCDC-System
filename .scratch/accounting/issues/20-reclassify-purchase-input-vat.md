# 20: Reclassify allowable purchase VAT with reviewed cost allocations

**What to build:** An accountant can record allowable purchase VAT through a source-linked journal with reviewed remaining/consumed-cost allocations, preserving AP, physical quantities and the POS-only tax-report boundary.

**Blocked by:** 18: Correct posted purchases and track supplier refunds due.

**Status:** ready-for-agent

- [ ] Capture the purchase source, accountant-approved allowable VAT, allocation amounts, supporting rationale and authorization; do not infer eligibility or an automatic receipt-cost split.
- [ ] Input VAT debit equals credits to remaining Inventory, attributable consumed cost and other original purchase accounts; validate corrected source amount, previously reclassified amounts and remaining carrying value.
- [ ] Update remaining inventory carrying value and its linked GL effects atomically; preserve physical quantity, original sale COGS and the supplier obligation rather than reducing AP again.
- [ ] Support inventory and non-inventory purchases and respect source corrections/refund adjustments when determining still-available amounts.
- [ ] Require active approved accounts, valuation/post permission, open date and forward-only rules; duplicates/excess/failed posting have no partial effects.
- [ ] Show source and reclassification detail in the authorized journal/purchase history; keep tax pages POS-only and do not add an input-VAT capture/filing module.
- [ ] Demonstrate an explicitly approved split between remaining Inventory and consumed cost, and reject over-reclassification or mismatched allocation totals; cover unchanged AP/quantity/history.
