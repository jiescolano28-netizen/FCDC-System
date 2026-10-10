# 11: Post directly paid inventory purchases

**What to build:** A cash/bank/check inventory purchase creates one valued stock receipt and one payment posting without creating and paying a fictitious supplier payable.

**Blocked by:** 06: Post valued non-sale stock movements; 09: Post direct cash, bank and released-check disbursements.

**Status:** ready-for-agent

- [ ] Capture the direct purchase payee/reference/evidence and received item quantities/values alongside the disbursement; require confirmed receipt and approved mappings.
- [ ] Atomically debit Inventory, credit the actual money account and record the linked physical/valued receipt; do not generate AP or another standalone receipt.
- [ ] Mixed direct payments allocate inventory and non-inventory amounts exactly to the payment total; preserve actual gross purchase costs pending authorized VAT reclassification.
- [ ] Apply moving-average valuation, full-depletion precision, authority, open-period and forward-only rules consistently with other valued movements.
- [ ] Show purchase/payment/receipt links in the existing detail/history surface and preserve immutable posted identification.
- [ ] Demonstrate a directly paid receipt changing the moving-average value with no AP balance, and reject duplicate physical receipt or failed payment posting without partial effects.
