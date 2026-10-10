# 06: Post valued non-sale stock movements

**What to build:** Authorized staff can record non-sale receipts, project issues, losses and counted adjustments that change quantity, inventory value and the linked accounting entry together.

**Blocked by:** 03: Post and correct persistent manual journals; 05: Approve opening quantities and inventory values.

**Status:** ready-for-agent

- [ ] Require reason, approved accounting counterpart, applicable valuation authorization and source links; quantity-only completion cannot bypass accounting after cutover.
- [ ] Issue/decrease value uses the remaining moving-average quantity/value, debits the approved project-use/loss/adjustment account and credits Inventory; increases require approved value and credit counterpart.
- [ ] Value, quantity and balanced journal effects commit atomically; rejected permission, mapping, date, quantity or valuation leaves no partial movement or posting.
- [ ] Reject effective valuation timestamps before the latest valued movement of that item; order same-date movements deterministically and honor closed-period/cutover/nonfuture rules.
- [ ] Round each posted movement value to centavos; full depletion consumes all remaining carrying value rather than stranding rounding residuals. Preserve quantity and valuation history.
- [ ] Update the displayed accounting cost from the valued schedule, not an independently editable item cost; do not infer Sales/AP or POS COGS for non-sale movements.
- [ ] Demonstrate 10 units at PHP 100 plus 10 units at PHP 200 giving 20 units/PHP 3,000, then a 2-unit issue worth PHP 300; cover full depletion, backdating rejection and rollback.
