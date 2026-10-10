# 18: Correct posted purchases and track supplier refunds due

**What to build:** An accountant can correct a credit or directly paid purchase after payment/consumption without deleting receipt/payment history, and record a refund receivable when the supplier owes money back.

**Blocked by:** 10: Allocate and reverse supplier payable payments; 11: Post directly paid inventory purchases.

**Status:** ready-for-agent

- [ ] Use reasoned source-linked reversal/replacement or correction chains with preserved originals and distinct active corrected identities; honor duplicate-invoice rules without losing history.
- [ ] A financial correction preserves real received quantity unless an explicit authorized physical correction is also completed; adjust remaining/consumed costs and supplier obligations through controlled linked entries.
- [ ] Keep existing payment allocations traceable; never cascade-delete payments or stock or create unsupported negative quantities/carrying values.
- [ ] If corrected purchase value is below valid amounts already paid, AP outstanding stays zero and the excess is a linked Supplier Refund Receivable, not an intentional advance or silently applied supplier credit.
- [ ] A linked authorized Cash/Bank receipt journal clears an actual refund; display correction/refund history and outstanding amounts in purchase/payable detail and print.
- [ ] Enforce posting/valuation authorization, reviewed allocation evidence, active accounts, period and forward-only rules, with all schedule and GL effects atomic.
- [ ] Demonstrate a fully paid PHP 50,000 purchase corrected to PHP 40,000 creating PHP 10,000 refund due, then record the refund; cover already-consumed stock and rollback without original-history edits.
