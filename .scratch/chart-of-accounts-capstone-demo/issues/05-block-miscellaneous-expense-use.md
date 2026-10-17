# 05: Block Miscellaneous Expense until its policy is approved

**What to build:** Miscellaneous Expense remains unavailable while its evidence, significance, and review policy is unresolved. No posting surface can use it as a fallback, balancing plug, or substitute for a specific account.

**Blocked by:** 03: Preserve safe account maintenance and approval

**Status:** ready-for-agent

- [x] Account 6190 is rejected server-side even when test setup makes it active and ordinarily approved; ordinary account or mapping approval does not release the restriction.
- [x] Manual journals, supplier purchase allocations, source reconciliation, direct disbursements, valued stock movements and corrections, supplier purchase corrections, and purchase-VAT reclassification reject prohibited 6190 use.
- [x] Rejections preserve each operation's source, journal, stock, AP, cash, and audit state at its existing atomic boundary.
- [x] Unmapped or unapproved sources never fall back to 6190; valid use of unrelated approved accounts remains available.
- [x] No invented threshold, evidence-sufficiency rule, or transaction-review policy is used to enable 6190.
