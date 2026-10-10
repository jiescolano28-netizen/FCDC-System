# 08: Post received supplier credit purchases

**What to build:** An accountant can post a mixed supplier invoice that recognizes AP, acquired assets/expenses and valued inventory receipts exactly once, then monitor and print the payable.

**Blocked by:** 04: Maintain suppliers and reconcile opening payables; 06: Post valued non-sale stock movements.

**Status:** ready-for-agent

- [ ] Persist editable/deletable invoice drafts with supplier, invoice number, recognition date, due date, gross amount, description, terms and allocated inventory/non-inventory lines; drafts have no financial or stock effects.
- [ ] Require confirmed receipt and approved allocations; inventory lines debit Inventory and create valued receipts, while non-inventory lines debit the actual approved asset/expense accounts; the gross invoice credits AP.
- [ ] Invoice, physical receipt, value and source-linked journal complete atomically; do not also allow a standalone receipt for the same purchase or infer allowable input VAT.
- [ ] Enforce normalized supplier/invoice uniqueness, authority, active mappings, open periods and forward-only valuation; advance invoices stay drafts and unmatched timing remains an authorized accrual-journal concern.
- [ ] Display posted purchase totals, outstanding and overdue, with search/filter by supplier, reference, dates and derived status; support detail and printable invoice/receipt/allocation history.
- [ ] Recognize PHP 50,000 equipment on credit as Equipment/AP, not an operating expense; demonstrate a mixed received invoice and weighted-average inventory receipt.
- [ ] Cover duplicate invoice, unsupported receipt/amount, missing mapping and transactional failure without partial AP/stock/journal effects.
