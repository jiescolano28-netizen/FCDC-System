# 14: Generate the as-of Trial Balance and reconciliation status

**What to build:** An accountant can generate cumulative account balances with period movement support and see whether both the books and their AP/Inventory schedules reconcile.

**Blocked by:** 04: Maintain suppliers and reconcile opening payables; 05: Approve opening quantities and inventory values; 13: Browse the posted General Ledger with running balances.

**Status:** ready-for-agent

- [ ] Select a period/end date and show opening, in-period debit/credit movement and cumulative closing balances; place each closing amount in its actual debit or credit column.
- [ ] Include approved openings, preceding postings, corrections and inactive accounts with balances; do not confuse movement-only totals with Trial Balance.
- [ ] Compare exact PHP-centavo closing totals and show an accounting error when unequal; never introduce a balancing plug.
- [ ] Reconcile controlled AP to supplier/invoice balances and Inventory to valued-item carrying values at the same as-of date; separately explain schedule mismatches even when debits equal credits.
- [ ] Respect accounting visibility, supported historical coverage and inclusive Manila dates; show zero activity honestly only in an activated empty book.
- [ ] Demonstrate a no-activity month retaining opening balances and a deliberately inconsistent isolated schedule producing an error; cover debit/credit-side and cumulative-period boundaries.
