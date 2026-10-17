# FCDC Chart of Accounts: Requirements Alignment and Gap Analysis

## 1. Executive Summary

**The COA maintenance framework is substantially implemented; the proposed chart is not present in the inspected database.** The application supports account and mapping maintenance, separate approval, status control, and posting eligibility checks. None of the 54 proposed account codes or equivalent accounts was found.

The repository’s prior investigation report (`docs/chart-of-accounts-investigation.md`) broadly matches the current source. The approved FCDC requirements PDF was not found in the repository paths searched, so the proposed accounts and names below are compared against the list supplied in the request. The PDF’s conditional notes could not be independently checked. `docs/accounting-workflow-design.md` provides relevant accounting rules, but does not establish that every proposed account must be created.

**Read-only investigation:** No source or database records were changed; no migrations or seeders were run.

## 2. Current COA Implementation

- `app/Livewire/Accounting/ChartOfAccounts.php:119–242` manages account creation/editing, activation, deletion, posting mappings, and approvals.
- `app/Models/AccountingAccount.php:12–64` resets approval on qualifying changes, prevents changing code/type/classification on used accounts, blocks deletion of used accounts, and requires an active approved account for posting.
- `app/Models/AccountingPostingMapping.php:10–56` defines mapping classification requirements and checks posting eligibility.
- The implementation supports five account types—Asset, Liability, Equity, Revenue, Expense—with hardcoded classifications. COGS is an Expense classification, not a sixth account type (`ChartOfAccounts.php:12–36`).
- Account codes are unique, but no numeric series or numbering convention is enforced (`ChartOfAccounts.php:122–129`; `database/migrations/2026_10_10_020302_create_accounting_accounts_table.php:14–27`).
- The UI accepts Debit or Credit as normal balance for every valid type/classification combination; it does not enforce normal-balance rules by account type or contra treatment (`ChartOfAccounts.php:122–129`).
- Financial statement groups are hardcoded (`app/Livewire/Accounting/FinancialStatements.php:17–50, 157–184, 219–268`). Current earnings are derived separately rather than stored as a dedicated COA account.
- COA route access requires authentication and `accounting.view`; maintenance and approval use separate permissions (`routes/web.php:67–74`; `app/Support/RolePermissionCatalog.php:37–42`).

**Database inspected:** `php artisan tinker` reported environment `local`, default connection and driver `mysql`, database `project`. It contained one account: `Sample` / `Journal Account`, Asset / `cash`, Debit, active, unapproved, unused. One unapproved `cash` mapping references that account. It had no journal lines, YTD lines, or other queried account-ID references. This workstation database is not evidence of production data.

## 3. Complete 54-Account Comparison

**Database finding:** All 54 proposals are missing. No exact code, equivalent account, or conflicting use of a proposed code was found. None has a current account type, classification, balance, active state, approval state, transaction usage, or mapping reference. The sample account and its cash mapping are separate records.

“Expected” account types, classifications, and normal balances below are **accounting conventions for analysis, not approved FCDC treatments**. Account applicability and the PDF’s conditional markings remain unverified.

| Account Code | Required Account Name | Existing Account | Account Type | Classification | Normal Balance | Active / Approved | Usage / Dependencies | Assessment | Evidence |
|---|---|---|---|---|---|---|---|---|---|
| 1010 | Cash on Hand | Missing | None; expected Asset | `cash` | None; expected Debit [convention] | No record | None; existing cash mapping points to unrelated sample account | Add | DB; `ChartOfAccounts.php:15, 38–50` |
| 1020 | Cash in Bank | Missing | None; expected Asset | `bank` | None; expected Debit [convention] | No record | None; bank mapping absent | Add | DB; `ChartOfAccounts.php:16, 38–50` |
| 1030 | Accounts Receivable | Missing | None; expected Asset | `accounts_receivable` | None; expected Debit [convention] | No record | None; GL-only AR is permitted by workflow design | Add | DB; `ChartOfAccounts.php:17`; `docs/accounting-workflow-design.md:19, 52` |
| 1040 | Input VAT | Missing | None; expected Asset | `input_vat` | None; expected Debit [convention] | No record | None; input VAT is used by approved supplier-purchase VAT reclassification | Add | DB; `ChartOfAccounts.php:25`; workflow design: 20, 53 |
| 1050 | Inventory — Recovered Construction Materials | Missing | None; expected Asset | `inventory` | None; expected Debit [convention] | No record | None; inventory/recovery mapping role exists; separate recovered-material account not confirmed | Add | DB; `ChartOfAccounts.php:19, 45`; workflow design: 32 |
| 1060 | Inventory — Other Resale Materials | Missing | None; expected Asset | `inventory` | None; expected Debit [convention] | No record | None; no specific inventory account is mapped | Needs Approval | DB; `ChartOfAccounts.php:19, 45`; separate account need unconfirmed |
| 1070 | Prepaid Expenses | Missing | None; expected Asset | `other_current_asset` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:21`; use and bucket unconfirmed |
| 1080 | Advances to Employees/Suppliers | Missing | None; expected Asset | `other_current_asset` | None; expected Debit [convention] | No record | None; supplier advances are not supported for AP settlement | Needs Approval | DB; `ChartOfAccounts.php:21`; workflow design: 25, 135 |
| 1500 | Property and Equipment | Missing | None; expected Asset | `equipment` | None; expected Debit [convention] | No record | None; equipment purchases use approved asset accounts | Add | DB; `ChartOfAccounts.php:20`; workflow design: 9, 141 |
| 1510 | Accumulated Depreciation | Missing | None; expected Asset, contra-asset | No dedicated contra class; `equipment` is closest | None; expected Credit [convention] | No record | None; reports allow opposite-side balances but lack a dedicated contra classification/presentation | Needs Approval | DB; `ChartOfAccounts.php:14–36`; `FinancialStatements.php:21–50, 248–260` |
| 2010 | Accounts Payable | Missing | None; expected Liability | `accounts_payable` | None; expected Credit [convention] | No record | None; AP mapping required for supplier settlements | Add | DB; `ChartOfAccounts.php:23, 46`; `AccountingPostingMapping.php:18` |
| 2020 | Accrued Expenses Payable | Missing | None; expected Liability | `other_current_liability` | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:26`; accrual use/classification unconfirmed |
| 2030 | Output VAT | Missing | None; expected Liability | `output_vat` | None; expected Credit [convention] | No record | None; POS posting requires approved Output VAT mapping | Add | DB; `ChartOfAccounts.php:24, 43`; `AccountingPostingMapping.php:15` |
| 2040 | VAT Payable | Missing | None; expected Liability | `other_current_liability` or `output_vat`, depending on meaning | None; expected Credit [convention] | No record | None; possible duplication with Output VAT | Needs Approval | DB; `ChartOfAccounts.php:23–27`; workflow design: 20, 123 |
| 2050 | Salaries and Wages Payable | Missing | None; expected Liability | `other_current_liability` | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:26` |
| 2060 | Other Taxes Payable | Missing | None; expected Liability | `other_current_liability` | None; expected Credit [convention] | No record | None; tax accounts are not inferred automatically | Needs Approval | DB; `ChartOfAccounts.php:26`; workflow design: 20, 123 |
| 2070 | Customer Advances | Missing | None; expected Liability | `other_current_liability` | None; expected Credit [convention] | No record | None; applicability depends on actual customer-payment workflow | Needs Approval | DB; `ChartOfAccounts.php:26`; workflow design: 19 |
| 2080 | Loans Payable | Missing | None; expected Liability | `other_noncurrent_liability` or `other_current_liability`, depending on terms | None; expected Credit [convention] | No record | None; maturity not provided | Needs Approval | DB; `ChartOfAccounts.php:27` |
| 3010 | Capital Stock | Missing | None; expected Equity | `capital` | None; expected Credit [convention] | No record | None; requested corporate account, not sole-proprietorship equity | Add | DB; `ChartOfAccounts.php:28`; workflow design: 42 |
| 3020 | Additional Paid-in Capital | Missing | None; expected Equity | `other_equity` | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:30`; corporate presentation unconfirmed |
| 3030 | Retained Earnings | Missing | None; expected Equity | `retained_earnings` | None; expected Credit [convention] | No record | None; fiscal-year closing requires this classification | Add | DB; `ChartOfAccounts.php:29`; `FiscalYearClosingService.php:55–58` |
| 3040 | Current-Year Profit or Loss | Missing | None; expected Equity or derived presentation | No dedicated class; current earnings are derived | Not a normal posting balance; treatment depends on decision | No record | None; adding a posting account risks double-counting derived unclosed earnings | Needs Approval | DB; `FinancialStatements.php:263–268`; workflow design: 42, 54 |
| 3050 | Dividends Declared | Missing | None; expected Equity, contra-equity | `other_equity` is closest; no dividends class | None; typically Debit [convention] | No record | None; no specialized contra-equity presentation | Needs Approval | DB; `ChartOfAccounts.php:28–30`; `FinancialStatements.php:21–50` |
| 4010 | Sales — Recovered Construction Materials | Missing | None; expected Revenue | `sales` | None; expected Credit [convention] | No record | None; POS uses a Sales mapping; recovered-material revenue treatment requires approval | Add | DB; `ChartOfAccounts.php:31, 42`; POS mapping |
| 4020 | Sales — Other Materials | Missing | None; expected Revenue | `sales` | None; expected Credit [convention] | No record | None; separate account not required by existing mapping | Needs Approval | DB; `ChartOfAccounts.php:31`; operations/reporting need unconfirmed |
| 4030 | Demolition Service Revenue | Missing | None; expected Revenue | `sales` or `other_income`, depending on presentation | None; expected Credit [convention] | No record | None; reporting bucket requires confirmation | Needs Approval | DB; `ChartOfAccounts.php:31–32`; `FinancialStatements.php:173–184` |
| 4040 | Other Operating Revenue | Missing | None; expected Revenue | `sales` or `other_income`, depending on presentation | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:31–32`; statement bucket unconfirmed |
| 4090 | Sales Returns and Allowances | Missing | None; expected Revenue, contra-revenue | `sales` is closest; no contra-revenue class | None; expected Debit [convention] | No record | None; current Sales grouping does not display returns separately | Needs Approval | DB; `ChartOfAccounts.php:31`; `FinancialStatements.php:157–184` |
| 5010 | Cost of Goods Sold — Recovered Materials | Missing | None; expected Expense | `cost_of_goods_sold` | None; expected Debit [convention] | No record | None; POS uses approved COGS mapping; recovery treatment is policy-dependent | Add | DB; `ChartOfAccounts.php:33, 44`; `AccountingPostingMapping.php:16` |
| 5020 | Cost of Goods Sold — Other Materials | Missing | None; expected Expense | `cost_of_goods_sold` | None; expected Debit [convention] | No record | None; separate account not required by existing mapping | Needs Approval | DB; `ChartOfAccounts.php:33`; category need unconfirmed |
| 5030 | Approved Rework Costs Included in Inventory Cost | Missing | None; expected Inventory asset while capitalized; Expense/COGS upon consumption may apply | `inventory` while capitalized; COGS is not a substitute for capitalization | Depends on event; no single normal balance | No record | None; existing flows support Inventory and consumed expenses, not this hybrid name | Needs Approval | DB; workflow design: 24, 35; `ChartOfAccounts.php:19, 33` |
| 5090 | Inventory Adjustments / Shrinkage | Missing | None; expected Expense for losses; treatment depends on adjustment | `other_expense` or approved Expense class; not automatically COGS | None; expected Debit for losses [convention] | No record | None; adjustment counterpart mapping exists, but no dedicated loss account | Needs Approval | DB; `ChartOfAccounts.php:35, 49`; workflow design: 33 |
| 6010 | Salaries and Wages Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6020 | Rent Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6030 | Utilities Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6040 | Transportation and Delivery Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None; current use not verified | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6050 | Repairs and Maintenance Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6060 | Fuel and Lubricants Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6070 | Tools and Supplies Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None; capitalization/consumption policy may affect treatment | Needs Approval | DB; `ChartOfAccounts.php:34`; workflow design: 24 |
| 6080 | Equipment Maintenance Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6090 | Communication and Internet Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6100 | Office Supplies Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None; capitalization/consumption policy may affect treatment | Needs Approval | DB; `ChartOfAccounts.php:34`; workflow design: 24 |
| 6110 | Depreciation Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None; depreciation posting/schedule not verified | Needs Approval | DB; `ChartOfAccounts.php:34`; no depreciation feature found in inspected sources |
| 6120 | Permits and Licenses Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6130 | Professional Fees | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34` |
| 6140 | Bank Charges | Missing | None; expected Expense | `operating_expense` or `other_expense`, depending on policy | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:34–35` |
| 6150 | Insurance Expense | Missing | None; expected Expense | `operating_expense` | None; expected Debit [convention] | No record | None; prepaid portion may require separate treatment | Needs Approval | DB; `ChartOfAccounts.php:21, 34` |
| 6160 | Other Taxes and Fees Expense | Missing | None; expected Expense | `operating_expense` or `other_expense`, depending on policy | None; expected Debit [convention] | No record | None; distinguish expense from payable and VAT accounts | Needs Approval | DB; `ChartOfAccounts.php:23–27, 34–35` |
| 6190 | Miscellaneous Expense | Missing | None; expected Expense | `other_expense` | None; expected Debit [convention] | No record | None; no account-specific posting restriction found | Needs Approval | DB; `ChartOfAccounts.php:35`; `ManualJournalService.php:241–248` |
| 7010 | Other Income | Missing | None; expected Revenue | `other_income` | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:32`; `FinancialStatements.php:173–184` |
| 7020 | Interest Income | Missing | None; expected Revenue | `other_income` | None; expected Credit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:32` |
| 7030 | Interest Expense | Missing | None; expected Expense | `other_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:35`; `FinancialStatements.php:173–184` |
| 7040 | Loss on Disposal of Equipment | Missing | None; expected Expense | `other_expense` | None; expected Debit [convention] | No record | None; disposal workflow not verified | Needs Approval | DB; `ChartOfAccounts.php:35` |
| 7090 | Other Non-Operating Expenses | Missing | None; expected Expense | `other_expense` | None; expected Debit [convention] | No record | None | Needs Approval | DB; `ChartOfAccounts.php:35`; `FinancialStatements.php:173–184` |

## 4. KEEP

- Keep the persisted maintenance, separate account/mapping approval, and posting-eligibility mechanisms (`ChartOfAccounts.php:119–242`; `AccountingAccount.php:61–64`; `AccountingPostingMapping.php:48–56`).
- Keep unique account codes. The mechanism exists, though it does not enforce a number-series convention (`2026_10_10_020302_create_accounting_accounts_table.php:16`; `ChartOfAccounts.php:122–124`).
- Keep used-account protection and deactivation rather than deletion for historical accounts (`AccountingAccount.php:14–33`; `docs/accounting-workflow-design.md:41, 76`).
- Keep the five account types and COGS-as-Expense design (`ChartOfAccounts.php:12–36`; workflow design: 40).
- Keep corporate equity accounts; do not substitute sole-proprietorship accounts (`docs/accounting-workflow-design.md:42`).

No proposed account record is already present to retain as a compliant chart record.

## 5. MODIFY

| Finding | Current behavior | Suggested modification | Files / integration impact | Risk |
|---|---|---|---|---|
| Normal balance | Debit/Credit accepted for every account type/classification (`ChartOfAccounts.php:122–129`). | Define and validate approved normal-balance behavior, including legitimate contra accounts. | COA validation/model and reporting consumers. | High: incorrect direction can distort reports. |
| Contra-asset | Statements can show a credit balance in an Asset class but have no dedicated contra class/presentation (`FinancialStatements.php:248–260`). | Decide classification and display for Accumulated Depreciation. | Classification list and financial statements. | High: asset presentation. |
| Contra-revenue | Sales Returns could reduce Sales through debit activity, but are not displayed separately (`FinancialStatements.php:157–184`). | Decide whether returns are netted or shown separately. | Classification/report logic and mapping strategy. | Medium–high: revenue/Gross Profit presentation. |
| Current-year earnings | Balance Sheet derives unclosed earnings separately (`FinancialStatements.php:263–268`); closing transfers earnings to retained earnings. | Keep 3040 derived unless approved accounting design explicitly avoids double counting. | Financial statements, fiscal closing, opening/YTD. | High: equity double count. |
| VAT payable | Input VAT and Output VAT classifications exist; separate VAT Payable does not (`ChartOfAccounts.php:23–27`). | Decide whether VAT Payable is a separate account or redundant/aggregate presentation. | Mappings, reclassification, statements. | High: duplicate or misstated liability. |
| Mapping setup | Local DB has only a pending cash mapping to the sample account; other mapping rows absent. | Configure and approve mappings against approved accounts in the target environment. | POS, supplier/AP, cash, inventory services. | High: operational postings blocked or misdirected. |
| Miscellaneous Expense | No account-specific restriction found; no enforcement is evidenced for 6190. | If required by accounting approval, add a controlled policy at posting boundaries. | Journal/disbursement validation. | Medium: catch-all misuse. |
| Edit/delete protection | Code/type/classification of used accounts are immutable; deletion checks `used_at`, with some additional FK restrictions. | Preserve historical behavior; check all references before any future deletion policy change. | Account model and all account FK consumers. | High if protections are weakened. |

## 6. ADD

The following **12 Add candidates** correspond to account roles explicitly used by the workflow or requested corporate chart. They are not authorized for creation until the final list and treatment are approved:

- 1010 Cash on Hand
- 1020 Cash in Bank
- 1030 Accounts Receivable
- 1040 Input VAT
- 1050 Inventory — Recovered Construction Materials
- 1500 Property and Equipment
- 2010 Accounts Payable
- 2030 Output VAT
- 3010 Capital Stock
- 3030 Retained Earnings
- 4010 Sales — Recovered Construction Materials
- 5010 Cost of Goods Sold — Recovered Materials

These are not a complete or approved production chart; the PDF’s conditional notes were unavailable.

No new hierarchy, REST API, or separate COA service is supported by the reviewed requirements or dependencies.

## 7. REMOVE

**No removal is justified.** No proposed account record exists to remove. The origin of the sample-named account and mapping is unverified; preserve them unless the owning environment establishes that removal is safe. Do not remove historical records, mappings, or working functionality because they are absent from the proposal.

## 8. Accounting Integration Impact

These are **source-code findings**, not runtime integration test results. The inspected DB has no journal lines for its account; its pending cash mapping does not prove production posting readiness.

| Module | COA reference and compatibility | Impact / verification status |
|---|---|---|
| Journal Entry | Selects active, approved accounts and persists account IDs. Manual posting rejects uncontrolled AP/Inventory lines (`JournalEntry.php:160`; `ManualJournalService.php:242–248`). | Source integration exists. New classifications and balance rules need validation. Runtime untested. |
| General Ledger | Reads account-linked journal lines; displays active accounts and inactive accounts with activity (`GeneralLedger.php:53–99`). | Code-level account-ID integration. Preserve used account identity. Runtime untested. |
| Trial Balance | Aggregates account-linked lines; reconciliation uses AP/Inventory classifications (`TrialBalance.php:69–77, 95–105`). | Classification changes may affect reconciliation. Runtime untested. |
| Financial Statements | Uses hardcoded classification allowlists and buckets; derives unclosed earnings (`FinancialStatements.php:17–50, 157–184, 219–268`). | Contra accounts, returns, VAT payable, and 3040 require design decisions and possibly code changes. Runtime untested. |
| POS | Uses Cash/Bank/Card Clearing, Sales, Output VAT, COGS, and Inventory mappings (`PosSalePostingService.php:70–80`). | Mapping-based integration exists. Local DB lacks approved mappings. Test source exists; not run. |
| Inventory | Valued movements and recovery use approved Inventory/counterpart accounts. | Shared Inventory role is supported; separate recovered/other accounts are not established. Runtime untested. |
| Accounts Payable | Supplier settlement checks the approved AP mapping; records reference account IDs. | 2010 is an AP-role candidate. Changes may affect schedule reconciliation. Test source exists; not run. |
| Supplier Purchases | Lines reference account IDs; Inventory and approved Asset/Expense classifications drive posting (`SupplierPurchaseService.php:21–75` and posting validation). | New accounts may change eligibility; preserve historic identity and purchase links. Runtime untested. |
| Cash Disbursements | Uses money and allocation account IDs; validates Cash/Bank and approved mappings (`CashDisbursementService.php:234–250, 366–443`). | Requires approved money and allocation accounts. No special 6190 restriction found. Runtime untested. |
| VAT Compliance | VAT records/reports use POS VAT records; accounting ledger separately uses Output VAT mapping. Purchase Input VAT reclassification is outside POS tax-report scope (`docs/accounting-workflow-design.md:20, 53`). | Resolve Output VAT/VAT Payable relationship without treating POS estimates as company VAT balances. Runtime untested. |

## 9. Items Requiring CPA/Professor Approval

1. Final account list, applicability, and conditional accounts; confirm against the approved PDF and FCDC operations.
2. Corporate equity presentation, including Capital Stock, Additional Paid-in Capital, Retained Earnings, Dividends Declared, and whether Current-Year Profit or Loss is derived only.
3. Classification and presentation for Accumulated Depreciation, Dividends Declared, and Sales Returns and Allowances.
4. Whether VAT Payable is distinct from Output VAT; Input VAT treatment must be supported by accountant-approved evidence.
5. Whether recovered and other resale materials need separate Inventory accounts; treatment of approved rework costs.
6. Whether demolition service/other operating revenue belongs in Sales or Other Income; whether returns are presented separately.
7. Current/noncurrent liability treatment for loans and classification of accruals, payroll, taxes, and customer advances.
8. Expense applicability and policy, including Miscellaneous Expense restrictions, depreciation, disposal loss, shrinkage, and supplies.
9. Whether 1xxx–7xxx four-digit codes are a formal numbering convention; the application currently enforces uniqueness only.
10. Production chart and mappings. The inspected local `project` database is not production evidence.

## 10. Implementation Plan

1. **Validate the final approved FCDC account list.** Obtain the approved PDF; settle conditional accounts, equity, contra accounts, VAT, revenue presentation, and numbering. **Dependency:** required before chart changes.
2. **Correct approved classification and normal-balance rules.** Ensure statements, contra handling, Balance Sheet equation, Trial Balance, fiscal close, and opening/YTD treatment agree. **Dependency:** Stage 1 decisions.
3. **Create/update approved records without duplication.** Compare by code and equivalent account in the target environment; do not blindly insert all 54; preserve used identities and history. **Dependency:** approved chart and classifications.
4. **Configure source-to-account mappings.** Map applicable cash, bank, card clearing, sales, Output VAT, COGS, Inventory, AP, recovery, adjustment, and additional payment sources; approve mappings. **Dependency:** approved accounts.
5. **Verify dependent modules.** Trace eligibility and historical behavior through Journal Entry, GL, Trial Balance, Financial Statements, POS, Inventory, AP, Purchases, Disbursements, and VAT. **Dependency:** finalized classifications/mappings.
6. **Run automated and manual checks.** Test COA, journal, POS, Inventory, AP/Purchases, Disbursements, reports, VAT, contra balances, returns, and no double-counted current earnings. **Dependency:** implementation in a controlled test environment.
7. **Approve before production use.** Complete CPA/professor approval, opening balance/cutover reconciliation, and accounting activation prerequisites. **Dependency:** reconciled books and documented approvals.

## 11. Final Summary

Feature counts are separate from account-record counts. The 12 Add candidates and 42 Needs Approval records below are classifications of the 54 proposed records for this report—not authorization to create them.

| Category | Total | Description |
|---|---:|---|
| Keep | 5 features; 0 proposed account records | Existing compliant maintenance, approval, uniqueness, history protection, and account-type behavior |
| Modify | 8 findings; 0 proposed account records | Balance rules, contra/reporting, current earnings, VAT distinction, mappings, and protections |
| Add | 0 confirmed features; 12 candidate account records | Missing account-role candidates; creation requires approval |
| Remove | 0 | No justified removal |
| Needs Approval | 0 feature count; 42 proposed account records | Applicability, classification, presentation, or policy unresolved |

**Evidence limits:** The approved PDF and production database were unavailable. The local DB query was executed; tests were not run. No claim is made that production records or runtime module behavior match this workstation.
