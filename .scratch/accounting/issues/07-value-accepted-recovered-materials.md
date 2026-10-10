# 07: Value accepted recovered materials

**What to build:** Accepting recovered material posts its approved quantity and recovery value with the correct project/recovery counterpart, rather than fabricating a purchase or recovery sale.

**Blocked by:** 06: Post valued non-sale stock movements.

**Status:** implemented

- [x] Require an accountant-approved recovery/project cost policy, assigned value and approved counterpart before accepted stock completes; do not infer cost from selling price.
- [x] Accept/reject portions through the existing recovery assessment workflow; only accepted quantity enters valued inventory and rejection reasons remain visible.
- [x] Acceptance atomically links the immutable assessment, valued receipt, quantity and Inventory debit/counterpart credit; it creates neither AP nor invented Sales Revenue.
- [x] Apply approval, closed-period, nonfuture and forward-only chronology controls and preserve project/recovery provenance in detail/history.
- [x] Missing approval or failed posting leaves no accepted-stock or journal effect; no zero-cost fallback is introduced.
- [x] Demonstrate an authorized recovery acceptance and rejected unapproved valuation; cover accepted/rejected quantity boundaries and rollback of linked effects.
