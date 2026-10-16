# 09: Reconcile reports and preserve accounting boundaries

**What to build:** Posted accounting activity reconciles from journals through financial reports, while cutover, control-account, period, fiscal-year, and historical-account boundaries continue to constrain the books.

**Blocked by:** 04: Enforce special-account accounting treatment; 06: Preserve approved source mapping and POS posting; 07: Preserve AP and disbursement accounting flows; 08: Preserve inventory valuation and posting integrity

**Status:** implemented

- [x] Posted journals and General Ledger amounts reconcile; unadjusted and adjusted Trial Balances balance; Financial Statements satisfy Assets = Liabilities + Equity with unclosed earnings included exactly once.
- [x] Draft and unapproved entries are excluded from posted balances; legitimately posted historical accounts remain in reports after deactivation.
- [x] AP and Inventory control-account balances remain aligned with their supporting schedules; opening-book, approved cutover, and pre-cutover YTD rules remain intact.
- [x] Closed-period restrictions, fiscal-year closing, source-linked correction/reversal, and immutable posted-journal behavior remain intact.
- [x] No historical coverage, balancing amount, or report result is fabricated to force reconciliation.

**Verification:** `FinancialStatementsTest`, `GeneralLedgerTest`, `TrialBalanceTest`, `AccountsPayableTest`, `ValuedStockMovementTest`, `OpeningBooksTest`, `AccountingPeriodsTest`, and `FiscalYearClosingTest` exercised. Added a Trial Balance regression proving pre-cutover postings do not contaminate approved book balances. Full suite: 290 passed, 2 test failures from activation/fixture assumptions; after correcting those expectations/setup, `FiscalYearClosingTest`, `ProductionAccountingActivationTest`, and `TrialBalanceTest` passed (11 tests, 86 assertions). Report suites passed (21 tests, 164 assertions).
