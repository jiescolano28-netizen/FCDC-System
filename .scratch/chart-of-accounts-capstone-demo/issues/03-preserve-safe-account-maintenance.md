# 03: Preserve safe account maintenance and approval

**What to build:** Authorized maintainers can inspect, create, edit, activate, deactivate, and approve the proposed accounts through existing Chart of Accounts controls, while server-side rules protect account identity, approval history, and posting eligibility.

**Blocked by:** 02: Seed the complete proposed chart safely

**Status:** implementation-complete

- [x] The existing maintenance flow enforces unique codes, supported type/classification pairs, ordinary normal balances, and legitimate contra-account directions consistently in UI and business validation.
- [x] Relevant definition changes and reactivation require renewed approval; used account identities and historical references remain protected, and used accounts cannot be deleted.
- [x] Approval and maintenance actions require existing authorization and record genuine employee actors; unauthorized direct actions are rejected server-side.
- [x] Inactive proposals remain discoverable with the default listing and existing filters; inactive status, approval status, and posting eligibility are distinct.

**Implementation:** The existing account model owns the shared classification and normal-balance policy. Ordinary directions follow account type; proposed contra directions are explicitly limited to 1510/equipment/credit, 3050/other_equity/debit, and 4090/sales/debit. Model persistence rejects unsupported definitions and used-account normal-balance changes; posting eligibility requires an active, approved, valid definition. The existing Livewire controls retain separate maintenance and approval permissions and employee approver IDs. The listing shows status, approval, and posting eligibility separately and includes inactive accounts by default.

**Verification:** `php artisan test --compact tests/Feature/ChartOfAccountsTest.php` — 12 passed, 90 assertions. Affected account workflows: 36 passed, 283 assertions. `php vendor/bin/pint --test app/Livewire/Accounting/ChartOfAccounts.php app/Models/AccountingAccount.php tests/Feature/ChartOfAccountsTest.php tests/Feature/CashDisbursementsTest.php tests/Feature/TrialBalanceTest.php tests/Feature/InterveningSourceReconciliationTest.php` passed. Full suite: 278 passed, 3 pre-existing failures (`FiscalYearClosingTest`, `ProductionAccountingActivationTest`, and `ValuedStockMovementTest`); no account-maintenance failures.
