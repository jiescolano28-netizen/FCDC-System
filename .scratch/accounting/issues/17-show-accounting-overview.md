# 17: Show the real Accounting Overview and quick actions

**What to build:** Staff can see period revenue/expenses/profit and end-date balances together with recent posted journals and working accounting quick actions.

**Blocked by:** 16: Generate the corporate Balance Sheet without double-counted earnings.

**Status:** ready-for-agent

- [ ] Initially select the current Manila month; Revenue is VAT-exclusive total revenue, Expenses includes COGS and all expenses, and Net Income equals Revenue minus Expenses.
- [ ] Show AP outstanding, GL-only AR, valued Inventory and explicitly labeled Cash & Bank as of period end; card clearing is excluded from Cash & Bank.
- [ ] Recent entries include posted date/reference/source/description and link to detail; drafts and demonstration/session history are excluded.
- [ ] Quick actions open New Journal Entry, General Ledger, Trial Balance and Financial Statements; reuse their authorized surfaces and consistent date/scope rules.
- [ ] Before production activation display unavailable/unapproved, not fake zero balances; after activation distinguish a genuinely empty period from accumulated positions.
- [ ] Demonstrate a period with income and a prior-period AP/Cash position, showing correct flow-versus-position behavior and agreeing with the detailed reports; cover view permission and empty/unavailable boundaries.
