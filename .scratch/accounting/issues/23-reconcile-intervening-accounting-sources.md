# 23: Reconcile sources between cutover and activation

**What to build:** An accountant can review and include existing post-cutover source activity exactly once using approved valuation evidence, without duplicating its physical or operational records.

**Blocked by:** 12: Post paid POS sales and frozen COGS atomically; 19: Correct valued stock and recovery assessments safely; 20: Reclassify allowable purchase VAT with reviewed cost allocations.

**Status:** implemented; production activation remains gated by outstanding readiness blockers.

- [x] Identify existing POS, purchase, payment and stock events from approved cutover through activation readiness; exclude pre-cutover activity already represented by openings and all demonstration records.
- [x] Show matched, missing, unsupported-cost and duplicate-link states in an authorized review surface; require approved receipt/recovery/adjustment cost evidence, not guessed current item cost.
- [x] Include eligible sources in chronological order with immutable exactly-once accounting links; preserve original source identity and saved selling/VAT/payment amounts.
- [x] Reconstruct the valued-stock accounting timeline from approved evidence without duplicating physical receipts/issues, completed sales, linked VAT records or payments; preserve any already-posted frozen costs.
- [x] Respect source permissions, eligible dates/periods and financial-control rules; failed inclusion leaves no partial effects and repeating it does not duplicate postings.
- [x] Reconcile the resulting supplier/item schedules and ledger; unresolved quantities, carrying values or unsupported historical costs remain explicit activation blockers.
- [x] Demonstrate an already-existing sale/stock event being included once with no second quantity decrement, a repeat inclusion and an event rejected for missing historical receipt cost; cover reconciliation and atomic failure.
