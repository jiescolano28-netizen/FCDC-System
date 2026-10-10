# 10: Allocate and reverse supplier payable payments

**What to build:** A disbursement can partially settle one or more invoices of the same supplier, and a linked payment reversal restores their original outstanding balances.

**Blocked by:** 08: Post received supplier credit purchases; 09: Post direct cash, bank and released-check disbursements.

**Status:** ready-for-agent

- [ ] Select posted eligible invoices of one supplier and enter explicit positive allocations whose sum exactly equals the disbursement; reject unrelated suppliers, excess outstanding allocation and supplier advances.
- [ ] Debit AP and credit actual Cash/Bank without recognizing the purchase/expense again; payment, invoice allocations, controlled AP schedule and journal commit together.
- [ ] Derive paid/partially paid/unpaid and overdue from corrected invoice amounts, valid net allocations and due dates; users cannot toggle an unpaid invoice to Paid.
- [ ] Reversing a posted payment or returned released check restores its original invoice allocations through linked entries; preserve payment evidence, actor and reason without deleting history.
- [ ] Expose paid/outstanding/overdue totals, searchable payment detail and printable payable allocation/correction history; prevent competing payments from oversettling an invoice.
- [ ] Demonstrate PHP 35,000 allocated to PHP 30,000 and PHP 20,000 invoices, leaving PHP 15,000 outstanding, then reverse it and restore both balances.
- [ ] Cover excess allocation, same-supplier boundary, competing settlement and rollback with no partial AP/cash effects.
