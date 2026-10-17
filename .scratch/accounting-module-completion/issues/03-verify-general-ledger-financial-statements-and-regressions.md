# 03: Verify General Ledger and Financial Statements and fix regressions

**What to fix:** Confirm the General Ledger and Financial Statements already satisfy the
PDF contract, align the Chart of Accounts contra classifications, and remove the
unrelated regressions so the whole suite is green.

**Blocked by:** none

**Status:** resolved

- [x] Retained the dedicated `contra_asset` / `contra_equity` / `contra_revenue` classifications and updated the stale chart-of-accounts spec manifest and financial-statement tests to match.
- [x] Fixed the `InventoryManagementTest` timezone failure (Manila date vs UTC `now()`).
- [x] Fixed the `InterveningSourceReconciliation::includePosSale` date comparison (UTC-midnight date vs Manila datetime).
- [x] Verified General Ledger and Financial Statements behavior unchanged under the full suite.
