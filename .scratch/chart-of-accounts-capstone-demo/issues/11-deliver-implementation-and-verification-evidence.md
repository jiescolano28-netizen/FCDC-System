# 11: Deliver implementation and verification evidence

**What to build:** Reviewers receive an evidence-based account-by-account and phase-by-phase report that distinguishes implemented behavior, executed verification, unresolved accounting approvals, and unverified integrations.

**Blocked by:** 01–10; implementation evidence is available for the code paths described below, but persistent demo provisioning, selected integration proofs, and accounting approvals remain open.

**Status:** report delivered; overall rollout is not fully verified. Persistent demo seeding and rendered-demo verification are withheld pending independently verified provisioning.

- [x] Report implementation summary, changed files, database changes and effective target, including isolation evidence or why persistent seeding was withheld.
- [x] Provide the full 54-account chart grouped by the seven proposal categories, with definition, status, approval, and posting eligibility; report unrelated or supporting accounts separately.
- [x] Distinguish preserved behavior, implemented safeguards, integration passes/failures/unverified areas, and exact test commands with pass/fail/skip/incomplete counts.
- [x] Record outstanding accounting and operational decisions and the safe current state for affected accounts and workflows.
- [x] Classify each of the eight specified phases honestly, using the required completion statuses and explaining mixed or blocked work.

## 1. Implementation summary

The current implementation adds an explicitly invoked, fail-closed demo isolation verifier and an idempotent 54-account seeder, plus account-maintenance safeguards, special-account posting restrictions, and safeguards for 6190. Existing maintenance, source mapping, POS, AP, disbursement, inventory, and reporting paths remain in place; the implementation does not add opening balances, mappings, transactions, journals, or approvals to the demo seeder.

The live persistent setup was **not** run: `demo:verify-isolation` rejected the workstation because its environment is not allowlisted. That refusal is expected safe behavior, not successful persistent isolation verification. Account rows below describe the proposed seeder definitions and intended initial state, not records observed in a provisioned database.

## 2. Implementation files and report changes

Source and test files carrying the capstone implementation behavior (not a claim that every listed file was newly changed in this report's working-tree edit):

- `app/Support/DemoDatabaseIsolation.php`, `app/Console/Commands/VerifyDemoDatabaseIsolation.php`, `config/demo.php`, `config/database.php`: target checks and explicit demo runtime configuration.
- `database/seeders/ProposedChartOfAccountsSeeder.php`, `app/Console/Commands/SeedProposedChartOfAccounts.php`: explicit, transactional chart setup and result reporting.
- `app/Models/AccountingAccount.php`, `app/Livewire/Accounting/ChartOfAccounts.php`, `resources/views/livewire/accounting/chart-of-accounts.blade.php`: account validation, eligibility, maintenance behavior, and listing status.
- `app/Services/Accounting/PosSalePostingService.php`, `app/Services/Inventory/RecordValuedStockMovement.php`: shared posting restrictions at the relevant integration boundaries.
- `tests/Feature/DemoDatabaseIsolationTest.php`, `tests/Feature/ProposedChartOfAccountsSeederTest.php`, `tests/Feature/ChartOfAccountsTest.php`, `tests/Feature/CashDisbursementsTest.php`, `tests/Feature/InventoryManagementTest.php`, `tests/Feature/JournalEntryTest.php`, `tests/Feature/SupplierPurchaseInvoicesTest.php`, `tests/Feature/ValuedStockMovementTest.php`: executable behavioral coverage.
- `README.md`: explicit demo setup instructions.
- This report and phase issue records under `.scratch/chart-of-accounts-capstone-demo/issues/`: evidence and remaining decisions.

The current working tree contains unrelated or separately scoped changes, which are intentionally not attributed to this report. No database migration was added for the chart.

## 3. Database changes and effective target

No migration was added for the chart. The seeder creates definitions only and is not registered in ordinary `DatabaseSeeder` execution. No production/business configuration or records were changed by persistent setup. PHPUnit uses disposable in-memory SQLite; that proves test isolation, not persistent demo deployment. No effective persistent demo database was configured or seeded, so there are no persistent chart rows, seeded approvals, maps, journals, or transaction changes to report.

Isolation requires explicit `DEMO_ENABLED=true`, an allowlisted nonproduction `APP_ENV`, `DB_CONNECTION=demo`, matching audit activity routing, and independent identity checks against the actual write target. SQLite requires an existing canonical dedicated file under `storage/app/demo`, not an alias of another configured database. Server databases require matching configured/live endpoint identities and `DEMO_DB_ISOLATION_ATTESTED=true` backed by independently established dedicated resources and restricted credentials. Ambiguous URL/socket/read-write aliases and non-isolated session/queue/cache/job storage fail closed. Current workstation command result: `php artisan demo:verify-isolation` refused because the environment is not allowlisted; persistent isolation is therefore unverified and seeding was withheld.

## 4. Proposed chart: all 54 accounts

All listed definitions use the five existing account types; COGS is an Expense classification, not a sixth account type. On a new isolated target, each seeded row starts **Inactive / Unapproved / Not eligible for posting**, with no approver, approval timestamp, usage timestamp, mapping, balance, or transaction. On reruns, existing operational state/history is preserved; conflicts are reported, not overwritten. No persistent rows were observed in this environment. “Definition/condition” records the intended existing classification, normal balance, and qualification or policy note.

For every account row below, the `Initial state` shorthand means **Active status: No; Approval status: Pending/unapproved; Posting eligibility: No**. This is the intended state of a newly seeded account, not an observed state in an unprovisioned database. Policy-held accounts remain prohibited even if ordinary account approval is later granted.

### Assets — 1000 (10)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 1010 | Cash on Hand | Asset / `cash` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 1020 | Cash in Bank | Asset / `bank` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 1030 | Accounts Receivable | Asset / `accounts_receivable` | Debit | Preserve GL-only receivable scope | Inactive / unapproved / not postable |
| 1040 | Input VAT | Asset / `input_vat` | Debit | No automatic purchase-VAT eligibility | Inactive / unapproved / not postable |
| 1050 | Inventory — Recovered Construction Materials | Asset / `inventory` | Debit | No new category routing | Inactive / unapproved / not postable |
| 1060 | Inventory — Other Resale Materials | Asset / `inventory` | Debit | No new category routing | Inactive / unapproved / not postable |
| 1070 | Prepaid Expenses | Asset / `other_current_asset` | Debit | Validate actual treatment before use | Inactive / unapproved / not postable |
| 1080 | Advances to Employees/Suppliers | Asset / `other_current_asset` | Debit | No supplier-advance application workflow | Inactive / unapproved / not postable |
| 1500 | Property and Equipment | Asset / `equipment` | Debit | No asset-management expansion | Inactive / unapproved / not postable |
| 1510 | Accumulated Depreciation | Asset / `equipment` | Credit | Provisional contra-asset representation; presentation pending; policy-held | Inactive / unapproved / not postable |

### Liabilities — 2000 (8)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 2010 | Accounts Payable | Liability / `accounts_payable` | Credit | Preserve AP control-account rules | Inactive / unapproved / not postable |
| 2020 | Accrued Expenses Payable | Liability / `other_current_liability` | Credit | Validate actual accrual treatment | Inactive / unapproved / not postable |
| 2030 | Output VAT | Liability / `output_vat` | Credit | Existing singular POS VAT role | Inactive / unapproved / not postable |
| 2040 | VAT Payable | Liability / `other_current_liability` | Credit | Provisional; distinct use and settlement policy pending; policy-held | Inactive / unapproved / not postable |
| 2050 | Salaries and Wages Payable | Liability / `other_current_liability` | Credit | Validate applicable payroll liability | Inactive / unapproved / not postable |
| 2060 | Other Taxes Payable | Liability / `other_current_liability` | Credit | If applicable | Inactive / unapproved / not postable |
| 2070 | Customer Advances | Liability / `other_current_liability` | Credit | If applicable; no new advance module | Inactive / unapproved / not postable |
| 2080 | Loans Payable | Liability / `other_noncurrent_liability` | Credit | Provisional; if applicable; maturity classification pending actual terms | Inactive / unapproved / not postable |

### Equity — 3000 (5)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 3010 | Capital Stock | Equity / `capital` | Credit | Validate corporate records | Inactive / unapproved / not postable |
| 3020 | Additional Paid-in Capital | Equity / `other_equity` | Credit | If applicable | Inactive / unapproved / not postable |
| 3030 | Retained Earnings | Equity / `retained_earnings` | Credit | Preserve existing fiscal-year close role | Inactive / unapproved / not postable |
| 3040 | Current-Year Profit or Loss | Equity / `other_equity` | Credit | Provisional storage only; profit Credit/loss Debit; no ledger posting; policy-held | Inactive / unapproved / not postable |
| 3050 | Dividends Declared | Equity / `other_equity` | Debit | Provisional contra-equity representation; if applicable; policy pending; policy-held | Inactive / unapproved / not postable |

### Revenue — 4000 (5)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 4010 | Sales — Recovered Construction Materials | Revenue / `sales` | Credit | No new category routing | Inactive / unapproved / not postable |
| 4020 | Sales — Other Materials | Revenue / `sales` | Credit | No new category routing | Inactive / unapproved / not postable |
| 4030 | Demolition Service Revenue | Revenue / `sales` | Credit | Provisional operating-revenue grouping; if applicable | Inactive / unapproved / not postable |
| 4040 | Other Operating Revenue | Revenue / `sales` | Credit | Validate operating-revenue grouping before use | Inactive / unapproved / not postable |
| 4090 | Sales Returns and Allowances | Revenue / `sales` | Debit | Contra-revenue; existing net-sales calculation retained | Inactive / unapproved / not postable |

### Cost of Goods Sold — 5000 (4)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 5010 | Cost of Goods Sold — Recovered Materials | Expense / `cost_of_goods_sold` | Debit | No new category routing | Inactive / unapproved / not postable |
| 5020 | Cost of Goods Sold — Other Materials | Expense / `cost_of_goods_sold` | Debit | No new category routing | Inactive / unapproved / not postable |
| 5030 | Approved Rework Costs Included in Inventory Cost | Expense / `cost_of_goods_sold` | Debit | Provisional display/storage only; if applicable; capitalization versus expense unresolved; policy-held | Inactive / unapproved / not postable |
| 5090 | Inventory Adjustments / Shrinkage | Expense / `other_expense` | Debit | Subject to approval; not every adjustment is shrinkage | Inactive / unapproved / not postable |

### Operating Expenses — 6000 (17)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 6010 | Salaries and Wages Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6020 | Rent Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6030 | Utilities Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6040 | Transportation and Delivery Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6050 | Repairs and Maintenance Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6060 | Fuel and Lubricants Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6070 | Tools and Supplies Expense | Expense / `operating_expense` | Debit | No automatic capitalization decision | Inactive / unapproved / not postable |
| 6080 | Equipment Maintenance Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6090 | Communication and Internet Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6100 | Office Supplies Expense | Expense / `operating_expense` | Debit | No automatic capitalization decision | Inactive / unapproved / not postable |
| 6110 | Depreciation Expense | Expense / `operating_expense` | Debit | No depreciation schedule or automation | Inactive / unapproved / not postable |
| 6120 | Permits and Licenses Expense | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6130 | Professional Fees | Expense / `operating_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 6140 | Bank Charges | Expense / `operating_expense` | Debit | Demo operating-expense grouping; validate policy before use | Inactive / unapproved / not postable |
| 6150 | Insurance Expense | Expense / `operating_expense` | Debit | No automatic prepaid-versus-incurred decision | Inactive / unapproved / not postable |
| 6160 | Other Taxes and Fees Expense | Expense / `operating_expense` | Debit | As applicable; distinguish from VAT/payables | Inactive / unapproved / not postable |
| 6190 | Miscellaneous Expense | Expense / `other_expense` | Debit | Restricted incidental use; review/evidence/significance policy unresolved; policy-held | Inactive / unapproved / not postable |

### Other Income and Expenses — 7000 (5)

| Code | Account | Type / classification | Normal | Definition / condition | Initial state |
|---|---|---|---|---|---|
| 7010 | Other Income | Revenue / `other_income` | Credit | Ordinary proposed account | Inactive / unapproved / not postable |
| 7020 | Interest Income | Revenue / `other_income` | Credit | Ordinary proposed account | Inactive / unapproved / not postable |
| 7030 | Interest Expense | Expense / `other_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |
| 7040 | Loss on Disposal of Equipment | Expense / `other_expense` | Debit | If applicable; no disposal workflow added | Inactive / unapproved / not postable |
| 7090 | Other Non-Operating Expenses | Expense / `other_expense` | Debit | Ordinary proposed account | Inactive / unapproved / not postable |

**Count and unrelated/supporting accounts:** 10 + 8 + 5 + 5 + 4 + 17 + 5 = 54 distinct proposals. No persistent demo rows were observed because no target was provisioned, including any unrelated or supporting accounts; the proposal seeder created no supporting account. Card Settlement Receivable/Clearing is a separate potential supporting-account prerequisite, not part of the 54.

## 5. Preserved behavior and implemented safeguards

- **Behavioral verification recorded:** focused suites covered demo isolation/seeding, account maintenance, and selected POS/AP/disbursement/inventory/reporting behavior; exact latest focused commands and counts are in §10. These prove only the scenarios exercised by those suites, not every production integration or a persistent demo deployment.
- **Implemented safeguards:** Account maintenance retains the five supported types, unique codes, classification/normal-balance validation including legitimate contra directions, separate account and mapping approvals, genuine employee approval actors, reset of approval after relevant edits/reactivation, used-account identity protection, and used-account deletion prevention.
- **Accounting treatment:** 1510 uses Credit contra-asset treatment; in controlled fixtures its credit reduces assets. It remains policy-held pending presentation confirmation. 3050's Debit reduces equity in controlled fixtures but remains held pending applicability/policy. 4090's Debit reduces net sales under existing calculation.
- **Posting restrictions:** 3040 cannot be approved for ledger posting or posted directly; derived current-year earnings remain calculated once, with existing fiscal-year close behavior. 2040 does not duplicate POS Output VAT or infer settlement. 5030 remains non-postable pending approved rework capitalization/expense policy; receipt evidence does not change stock value or journals. 5090 is not a universal stock-adjustment/shrinkage destination. 6190 remains unavailable even if ordinary account approval is present; specified server-side paths reject its use.
- **Preserved but not fully verified:** Existing POS mapping remains single-destination, separately approved, atomic, and exactly once; missing/unapproved routing fails rather than guessing a proposed account. AP purchase recognition/settlement roles, source links, valued moving-weighted-average inventory chronology, source-linked corrections, reporting, and period/cutover boundaries were not intentionally redesigned. The latest integration run exercises selected paths; it is not complete evidence for every listed boundary.

## 6. Integration verification: passes, failures, and unverified work

**Recorded focused passes:** `php artisan test --compact tests/Feature/DemoDatabaseIsolationTest.php tests/Feature/ProposedChartOfAccountsSeederTest.php tests/Feature/ChartOfAccountsTest.php` passed 36 tests. The latest accounting integration command in §10 passed 87 tests. Earlier issue records contain historical separate runs and counts; those are not combined with current results.

**Recorded failures:** issue 01's 264-pass run reported four failures: `ChartOfAccountsTest` expected a different empty-state message; `FiscalYearClosingTest` and `ProductionAccountingActivationTest` could not satisfy production activation; `ValuedStockMovementTest` referenced undeclared Livewire property `stockItemId`. Issue 02's later run reported four failures across empty-state wording, accounting activation/cutover, and `stockItemId`. Issue 03's later run reported three failures: `FiscalYearClosingTest`, `ProductionAccountingActivationTest`, and `ValuedStockMovementTest`; the account-maintenance test passed. Those issue records characterize the failures as unrelated/pre-existing. The current full-suite result and attribution limit are recorded in §10.

**Unverified/blocked:** persistent demo connection/identity, actual persistent seeding and rerun, and rendered demo/non-demo COA comparison remain unverified because no safe persistent target/session exists. Phase 07 does not yet have a completed acceptance record for all AP/disbursement assertions. Phase 09 lacks full recorded reconciliation evidence for every requested boundary (AP/Inventory schedules, opening/cutover YTD, closed periods, reversals/reclose, immutable journals). VAT Compliance, Opening Books, Fiscal-Year Closing, card-settlement behavior, split-category routing, and any server-database-specific isolation behavior are not claimed as end-to-end verified by this report.

## 7. Tests and verification counts

Earlier exact command evidence:

- `php artisan test --compact tests/Feature/DemoDatabaseIsolationTest.php` — 15 passed, 0 failed, 0 skipped, 0 incomplete; 18 assertions (issue 01).
- `php artisan test --compact tests/Feature/ProposedChartOfAccountsSeederTest.php` — 7 passed, 0 failed, 0 skipped, 0 incomplete; 51 assertions (issue 02).
- `php artisan test --compact tests/Feature/ChartOfAccountsTest.php` — 12 passed, 0 failed, 0 skipped, 0 incomplete; 90 assertions (issue 03).
- `php artisan test --compact` (recorded issue 01 run) — 264 passed, 4 failed, 0 skipped, 0 incomplete; 2,454 assertions. Later distinct full runs: issue 02: 275 passed/4 failed; issue 03: 278 passed/3 failed. Do not add totals across different runs.
- `php artisan demo:verify-isolation` — expected safety refusal on unallowlisted workstation; 0 account writes. This is not a passing isolation/deployment verification.

Current verification counts and observed failure details are recorded in §10. Passing focused suites establish only the behaviors exercised by those tests; they do not replace persistent-target or rendered-interface verification.

## 8. Outstanding accounting and operational decisions; safe state

- **1510:** confirm contra-asset presentation. Inactive, unapproved, policy-held; no depreciation automation.
- **2040:** approve distinct liability and settlement purpose without duplicating 2030 POS Output VAT. Inactive and policy-held; no automatic mapping/netting.
- **3040:** resolve presentation without duplicating derived earnings. Visible only; ledger posting prohibited; continue existing closing process.
- **3050:** confirm corporate applicability and dividends policy/presentation. Inactive and policy-held.
- **5030:** determine approved treatment of rework (capitalization vs expense), applicability, and approval. Inactive and policy-held; no stock value or journal changes.
- **6190:** approve evidence sufficiency, materiality/significance and unusual-expense treatment, eligible incidental purpose, and transaction-level review. Inactive and unavailable even after ordinary account approval until policy is released; never fallback/plug.
- **5090:** define approval/purpose for inventory shrinkage; adjustments are not presumed shrinkage.
- Validate conditional/placeholder definitions: 1070 prepaid treatment, 1080 advance workflow, 2020 accrual basis, 2050 payroll applicability, 2060 other taxes applicability, 2070 customer advances, 2080 loan maturity, 3010 corporate records, 3020 applicability, 4030 operating-revenue grouping, 4040 classification, 6070/6100 capitalization, 6140 bank-charge classification, 6150 prepaid-versus-incurred, 6160 applicability, 7040 disposal applicability.
- Decide separately whether Sales 4010/4020, COGS 5010/5020, or Inventory 1050/1060 require category split-routing; current source mapping remains one destination per source. Determine whether approved card-POS demonstrations require a separately approved Card Settlement Receivable/Clearing account.
- Provision a dedicated persistent demo database and independent identity/isolation evidence. Without it, retain the workstation refusal and do not seed. Do not use production, business, shared, ambiguous, or merely demo-labeled databases.
- Complete end-to-end integration evidence for report reconciliation, AP/disbursement, VAT, opening/cutover, closed periods, and fiscal-year close before claiming full rollout completion.

## 9. Eight-phase completion status

| Phase | Required status | Evidence and limits |
|---|---|---|
| 1. Environment isolation and preparation | **Implemented but not fully verified** | Fail-closed checks implemented; 15 focused tests passed. Persistent target provisioning/identity is absent; live command refused on unallowlisted environment. |
| 2. Complete proposed chart | **Implemented but not fully verified** | Full manifest and transactional/idempotent seeder implemented; 7 focused tests passed. Persistent target not available; no real demo seed or rendered chart observed. |
| 3. Classification and validation | **Implemented but not fully verified** | Maintenance validation and special account guards implemented; controlled sign/eligibility scenarios recorded. Not every reporting/closing scenario has current complete integration evidence; policy-held proposals stay unavailable. |
| 4. Miscellaneous Expense safeguards | **Implemented but not fully verified** | Server-side 6190 restriction is recorded across specified posting entry points; policy itself unresolved, by design. Full independent verification of every reachable operation is not fully recorded in this delivery. |
| 5. Mappings and integrations | **Implemented but not fully verified** | Existing approved POS mapping behavior and fallback rejection are implemented/tested. Category split routing and supporting card-settlement need remain unresolved; complete integration matrix not proven. |
| 6. Demo interface | **Implemented but not fully verified** | Listing/status controls are updated, but no independently verified demo target/session exists to observe the exact conditional notice and compare with non-demo control. |
| 7. Automated testing | **Implemented but not fully verified** | Current focused runs pass (123 passed across two commands); the full suite reports 230 failures and 61 passes with attribution undetermined. Broad suite completion is not established. |
| 8. Verification and delivery | **Implemented but not fully verified** | This report records chart, safeguards, limits and decisions. Persistent isolation, full reconciliation, and some integrations remain open, preventing overall completion. |

These statuses do not imply accounting approval or CPA certification. No phase is classified **Completed and tested** while required evidence remains incomplete; phase 4's policy outcome is deliberately blocked by accounting approval, but safeguards themselves are implemented.


## 10. Delivery verification (this run)

- `php artisan test --compact tests/Feature/DemoDatabaseIsolationTest.php tests/Feature/ProposedChartOfAccountsSeederTest.php tests/Feature/ChartOfAccountsTest.php` — **36 passed, 0 failed, 0 skipped, 0 incomplete**; 186 assertions.
- `php artisan test --compact tests/Feature/PosSaleAccountingTest.php tests/Feature/AccountsPayableTest.php tests/Feature/SupplierPurchaseInvoicesTest.php tests/Feature/CashDisbursementsTest.php tests/Feature/ValuedStockMovementTest.php tests/Feature/InventoryReceiptAttachmentTest.php tests/Feature/FinancialStatementsTest.php tests/Feature/GeneralLedgerTest.php tests/Feature/TrialBalanceTest.php tests/Feature/InterveningSourceReconciliationTest.php tests/Feature/JournalEntryTest.php` — **87 passed, 0 failed, 0 skipped, 0 incomplete**; 795 assertions. This verifies the exercised feature tests, not every phase 5/7/9 criterion.
- `php artisan test --compact` — **61 passed, 230 failed, 0 skipped, 0 incomplete**; 420 assertions. Failures are dominated by SQLite `:memory:` cross-test isolation errors (`cannot VACUUM from within a transaction` and repeated `table "migrations" already exists`). The suite run does not establish whether any failures are attributable to this work, so broad integration success is not claimed. This current run supersedes earlier full-run counts as the latest observation; earlier numbers remain as historical separate runs.
- `php artisan demo:verify-isolation` — previously recorded refusal on the unallowlisted workstation; no persistent demo writes. No verified persistent demo target became available in this run.

**Review limit:** no browser session against an isolated demo target was available; exact notice visibility and demo/non-demo rendered comparison remain unverified.

