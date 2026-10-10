# 04: Maintain suppliers and reconcile opening payables

**What to build:** An accountant can maintain stable supplier identities, record opening unpaid invoices, and reconcile their schedule to the opening AP balance without recognizing purchases or stock again.

**Blocked by:** 02: Review the cutover and opening accounting books.

**Status:** ready-for-agent

- [ ] Maintain searchable supplier records with stable identity and preserved historical details; capture opening invoice number, recognition date, due date, amount, description and terms.
- [ ] Normalize surrounding whitespace/case for invoice uniqueness within one supplier; permit the same number for a different supplier and preserve linked historical reversals.
- [ ] Opening invoice amounts reconcile exactly to controlled opening AP lines and update the schedule and opening journal together; no duplicate purchase, expense or physical receipt is created.
- [ ] Show posted opening outstanding and overdue amounts and invoice detail/print with clear opening/draft/posted identification; due date is authoritative and state is derived, not freely editable.
- [ ] Restrict supplier maintenance, opening preparation and approval to authorized capabilities and record actor/approval history.
- [ ] Demonstrate an opening supplier schedule matching AP and rejection of a mismatch or duplicate invoice; cover supplier-specific uniqueness and schedule reconciliation.
