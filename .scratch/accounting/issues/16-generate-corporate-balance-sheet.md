# 16: Generate the corporate Balance Sheet without double-counted earnings

**What to build:** An accountant can view a dated Assets/Liabilities/Equity position, verify the accounting equation and distinguish approved capital, retained earnings and unclosed earnings.

**Blocked by:** 14: Generate the as-of Trial Balance and reconciliation status; 15: Generate the classified Income Statement.

**Status:** ready-for-agent

- [ ] Use cumulative posted as-of balances and approved classifications for Cash/Bank, AR, Inventory, Equipment, AP, applicable VAT and corporate equity.
- [ ] Determine unclosed earnings using approved openings/current-year activity and existing fiscal-close transfers, without duplicating amounts already included in retained earnings.
- [ ] Use actual ledger Input/Output VAT and authorized adjustments; never substitute the POS-only VAT estimate or infer legal registration/deductibility.
- [ ] Require Assets = Liabilities + Equity exactly in centavos; show an accounting error instead of an invented equity offset.
- [ ] Show date, scope, generation time and summary limitations, with permission/activation checks and no claim of audit, filing or legal certification.
- [ ] Demonstrate a balanced cash/equipment/AP/capital position with current earnings, and a failed accounting equation in isolated corrupted data; cover contra balances and earnings transfer semantics.
