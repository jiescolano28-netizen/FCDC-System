# 10: Present the chart as verified demo data

**What to build:** The existing Chart of Accounts makes the complete proposal inspectable in a verified demo runtime and unmistakably identifies illustrative data without changing the production presentation or replacing existing controls.

**Blocked by:** 01: Verify demo database isolation; 02: Seed the complete proposed chart safely

**Status:** implementation-complete; persistent demo provisioning and browser visual verification remain unavailable on this workstation

- [x] The exact notice “Illustrative / Sample Data — Not Actual FCDC Financial Records” appears only when the effective database is verified as the isolated demo target.
- [x] Production and non-demo contexts retain production-appropriate wording and never show demo labeling solely because a flag or UI label is set.
- [x] The default listing exposes all proposals, including inactive rows, and shows active status, approval status, posting eligibility, and pending-treatment reasons through existing suitable details.
- [x] Existing search, filters, maintenance, approval, and source-mapping controls remain available with their existing authorization boundaries.
- [x] Rendered demo and non-demo COA states are exercised in isolated feature tests. A browser visual check was unavailable because no persistent verified demo target is provisioned.

## Comments

**Implementation:** Chart presentation calls the existing `DemoDatabaseIsolation::verify()` against the effective database on each render. Only a successful verification enables the exact demo notice and illustrative wording. The default status filter remains `All`; account descriptions, including pending-treatment explanations, are fully visible.

**Verification:** `ChartOfAccountsTest.php` — 14 passed, 117 assertions; `DemoDatabaseIsolationTest.php` — 15 passed, 18 assertions; `ProposedChartOfAccountsSeederTest.php` — 7 passed, 51 assertions. Pint passed. Full suite: 289 passed, 2 failed (existing `FiscalYearClosingTest` general-ledger expectation and `ProductionAccountingActivationTest` wording expectation). `php artisan demo:verify-isolation` failed closed because the current environment is not allowlisted. No persistent demo database was configured or seeded; browser visual verification was not performed.

