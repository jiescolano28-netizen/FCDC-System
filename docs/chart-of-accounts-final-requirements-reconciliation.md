# FCDC Chart of Accounts — Final Requirements Reconciliation

**Status:** Read-only implementation planning. No source code or database records were changed.

## 1. Corrected requirements comparison

### Requirements evidence and correction to the prior gap analysis

The PDF supplied by the user is `E:/Downloads/Games/Dying_Light_2_--_fitgirl-repacks.site/Info in every Module.pdf`. Its pages 9–11 contain Section 4, “Chart of Accounts”; pages 11–12 contain the Miscellaneous Expense and journal-entry rules. The PDF says the 54 accounts are a **proposed** list for professor/CPA validation, must be adjusted to actual transactions, and require reuse of valid existing accounts and inspection of current mappings before adding or activating records (PDF extracted text around lines 329–358, 414–432).

The prior analysis’ “12 Add / 42 Needs Approval” split was **not a category defined by the PDF**. It was a speculative implementation grouping and should not be treated as approved account status. This reconciliation replaces it with PDF conditions plus operational roles. No proposal is presumed approved solely by appearing in the list.

**Verified database scope:** Earlier read-only query used environment `local`, MySQL connection, database `project`: one unapproved, unused `Sample` / `Journal Account` (Asset / `cash`) and one unapproved `cash` mapping to it. No proposed account code/equivalent was found. No production data were inspected. Existing DB references/callers must be rechecked in the actual deployment environment before implementation.

**Normal balance distinction:** PDF section 4 explicitly specifies the equity account type and normal-balance behavior for 3010–3050. It does not provide a normal-balance column for the other accounts in the extracted list. Debit/Credit suggestions below for those accounts are ordinary accounting expectations, **not PDF-finalized FCDC rules**.

| Code | PDF account | PDF condition / accounting instruction | Expected type / class for technical mapping | Normal balance | Decision status |
|---|---|---|---|---|---|
| 1010 | Cash on Hand | Proposed list; validate and reuse if applicable | Asset / `cash` | Debit [convention] | Candidate; verify equivalent and cash workflow |
| 1020 | Cash in Bank | Proposed list | Asset / `bank` | Debit [convention] | Candidate; verify equivalent and bank workflow |
| 1030 | Accounts Receivable | Proposed list | Asset / `accounts_receivable` | Debit [convention] | Candidate; project design permits GL-only AR; no customer subledger |
| 1040 | Input VAT | Proposed list; applicable VAT entries follow approved treatment | Asset / `input_vat` | Debit [convention] | Candidate; eligibility is not automatic |
| 1050 | Inventory — Recovered Construction Materials | Proposed list | Asset / `inventory` | Debit [convention] | Candidate; current source mapping uses a shared Inventory role |
| 1060 | Inventory — Other Resale Materials | Proposed list | Asset / `inventory` | Debit [convention] | Candidate; distinct account vs shared Inventory needs approval and code support |
| 1070 | Prepaid Expenses | Proposed list | Asset / `other_current_asset` | Debit [convention] | Validate actual balances/operations |
| 1080 | Advances to Employees/Suppliers | Proposed list | Asset / `other_current_asset` | Debit [convention] | Supplier-advance application is outside approved AP scope; confirm if this is only a GL/journal balance |
| 1500 | Property and Equipment | Proposed list | Asset / `equipment` | Debit [convention] | Candidate; equipment purchases use approved asset accounts |
| 1510 | Accumulated Depreciation | Proposed list; contra-asset treatment | Asset / needs contra-asset classification or approved equivalent | Credit [convention] | CPA approval of classification/presentation; technical report support required |
| 2010 | Accounts Payable | Proposed list | Liability / `accounts_payable` | Credit [convention] | Operational role required by supplier settlement workflow; reuse approved equivalent if any |
| 2020 | Accrued Expenses Payable | Proposed list | Liability / `other_current_liability` | Credit [convention] | Confirm actual accrual needs and treatment |
| 2030 | Output VAT | Proposed list | Liability / `output_vat` | Credit [convention] | POS posting role requires approved Output VAT mapping |
| 2040 | VAT Payable | Proposed list | Liability / `other_current_liability` or separate approved VAT class | Credit [convention] | Resolve whether distinct from Output VAT/net VAT position; do not duplicate liability |
| 2050 | Salaries and Wages Payable | Proposed list | Liability / `other_current_liability` | Credit [convention] | Confirm payroll liability applies |
| 2060 | Other Taxes Payable | **If applicable** | Liability / `other_current_liability` | Credit [convention] | Conditional; confirm actual taxes/obligations |
| 2070 | Customer Advances | **If applicable** | Liability / `other_current_liability` | Credit [convention] | Conditional; confirm transactions; no customer-advance module is authorized |
| 2080 | Loans Payable | **If applicable** | Liability / current or noncurrent classification by terms | Credit [convention] | Conditional; confirm actual loans/maturity |
| 3010 | Capital Stock | Corporation-appropriate equity | Equity / `capital` | **Credit (PDF)** | Use corporate model; validate corporate records |
| 3020 | Additional Paid-in Capital | **If applicable** | Equity / likely `other_equity` pending approved classification | **Credit (PDF)** | Conditional and subject to corporate-record validation |
| 3030 | Retained Earnings | Corporation-appropriate equity | Equity / `retained_earnings` | **Credit (PDF)** | Required role for fiscal-year close; reuse approved equivalent |
| 3040 | Current-Year Profit or Loss | Equity | Equity / no existing dedicated current-earnings class | **Credit for profit; Debit for loss (PDF)** | Resolve account record vs derived presentation; must avoid double count |
| 3050 | Dividends Declared | **If applicable; before production posting is enabled; Contra-Equity** | Equity / needs contra-equity class or approved equivalent | **Debit (PDF)** | Conditional; approval before production posting |
| 4010 | Sales — Recovered Construction Materials | Proposed list | Revenue / `sales` | Credit [convention] | Revenue role needed for POS; existing mapping supports one Sales destination |
| 4020 | Sales — Other Materials | Proposed list | Revenue / `sales` | Credit [convention] | Confirm actual sales category; split requires category-aware mapping/posting |
| 4030 | Demolition Service Revenue | **If applicable** | Revenue / `sales` or `other_income` by approved report policy | Credit [convention] | Conditional; confirm service transactions and presentation |
| 4040 | Other Operating Revenue | Proposed list | Revenue / `sales` or `other_income` by approved report policy | Credit [convention] | Confirm nature and statement bucket |
| 4090 | Sales Returns and Allowances | Proposed list; contra-revenue treatment implied by name | Revenue / `sales` or approved contra-revenue class | Debit [convention] | Approve net-vs-separate income-statement presentation |
| 5010 | Cost of Goods Sold — Recovered Materials | Proposed list | Expense / `cost_of_goods_sold` | Debit [convention] | COGS role needed for POS; one mapping currently supports one COGS destination |
| 5020 | Cost of Goods Sold — Other Materials | Proposed list | Expense / `cost_of_goods_sold` | Debit [convention] | Confirm category; separate COGS needs category-aware posting |
| 5030 | Approved Rework Costs Included in Inventory Cost | **If applicable**; CPA determines treatment; do not automatically expense or capitalize rework | Inventory while capitalized; Expense/COGS when consumed, under approved treatment | Depends on treatment | Conditional; CPA policy blocks setup |
| 5090 | Inventory Adjustments / Shrinkage | **Subject to approval** | Expense for approved losses; `other_expense`/approved Expense class | Debit for loss [convention] | Explicit approval and adjustment treatment required |
| 6010 | Salaries and Wages Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6020 | Rent Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6030 | Utilities Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6040 | Transportation and Delivery Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6050 | Repairs and Maintenance Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6060 | Fuel and Lubricants Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6070 | Tools and Supplies Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | CPA policy may distinguish supplies from capitalized/stock items |
| 6080 | Equipment Maintenance Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6090 | Communication and Internet Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6100 | Office Supplies Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | CPA policy may distinguish supplies from capitalized items |
| 6110 | Depreciation Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Account may be needed; depreciation calculation/schedule is not verified in code |
| 6120 | Permits and Licenses Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6130 | Professional Fees | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm applicable operating expense |
| 6140 | Bank Charges | Proposed list | Expense / `operating_expense` or `other_expense` by policy | Debit [convention] | Confirm reporting bucket |
| 6150 | Insurance Expense | Proposed list | Expense / `operating_expense` | Debit [convention] | Confirm prepaid vs incurred treatment |
| 6160 | Other Taxes and Fees Expense | **As applicable** | Expense / `operating_expense` or `other_expense` by policy | Debit [convention] | Conditional; distinguish from payable/VAT balances |
| 6190 | Miscellaneous Expense | Proposed list; restricted use (rules below) | Expense / `other_expense` | Debit [convention] | Add only with restricted posting behavior |
| 7010 | Other Income | Proposed list | Revenue / `other_income` | Credit [convention] | Confirm applicable transactions |
| 7020 | Interest Income | Proposed list | Revenue / `other_income` | Credit [convention] | Confirm applicable transactions |
| 7030 | Interest Expense | Proposed list | Expense / `other_expense` | Debit [convention] | Confirm applicable transactions |
| 7040 | Loss on Disposal of Equipment | **If applicable** | Expense / `other_expense` | Debit [convention] | Conditional; confirm disposal activity and workflow |
| 7090 | Other Non-Operating Expenses | Proposed list | Expense / `other_expense` | Debit [convention] | Confirm applicable transactions |

The table has 54 rows. PDF labels “proposed” do not mean that each account must be activated. Its explicit conditional entries are 2060, 2070, 2080, 3020, 3050, 4030, 5030, 6160 and 7040; 5090 is separately “subject to approval.” All accounts remain subject to review against actual transactions and CPA/professor validation.

## 2. Accounts to retain or reuse

- No proposed code or equivalent exists in the inspected local database. There is no verified proposed account record to retain/reuse from that database.
- Preserve the existing COA model, approval process, mapping model, five account types, persisted journal-account-ID relationships, and historic accounts. The architecture already has supported classifications for `cash`, `bank`, `accounts_receivable`, `card_clearing`, `inventory`, `equipment`, `accounts_payable`, `input_vat`, `output_vat`, `capital`, `retained_earnings`, `sales`, `cost_of_goods_sold`, `operating_expense`, and `other_expense` (`app/Livewire/Accounting/ChartOfAccounts.php:12–36`).
- The local sample `Sample` / `Journal Account` is active but unapproved and has only a pending cash mapping. Do not activate or relabel it as FCDC Cash without owner/source confirmation and approval.
- In the deployment database, reuse an approved equivalent by meaning and classification before adding a code. Never duplicate a code or remap historical journal lines without an approved correction plan.

## 3. Accounts proposed for addition

The PDF does not establish a blanket Add category. Account records should be proposed only after production-equivalent DB/mapping inspection and account-specific approval.

**Workflow roles requiring approved mappings before their corresponding operations are enabled:**

- Cash and Bank; Card Settlement Receivable/Clearing for card POS receipts (the current accounting workflow requires this role, but it is not one of the 54 PDF rows).
- Sales, Output VAT, COGS and Inventory for POS sales.
- Accounts Payable for supplier settlements.
- Inventory and an approved recovery/adjustment counterpart for inventory movements.
- Input VAT where an approved purchase-VAT reclassification is performed.
- Retained Earnings for fiscal-year closing.

These are workflow **roles**, not proof that specific proposed codes are mandatory. Current source has a single `sales`, `cogs`, `inventory` and `accounts_payable` mapping; split posting to 4010/4020, 5010/5020 or 1050/1060 is not supported by those single mappings without additional source/category selection logic. Configure only accounts needed for actual approved operations. The project’s GL-only AR and mixed asset/expense purchase functions may use approved classifications without introducing separate modules.

For every proposed record, including any ultimately added, set type/classification/normal balance from approved policy, mark inactive or pending approval until reviewed, and keep unapproved records unavailable to posting. Do not seed all 54.

## 4. Existing functionality to modify

### Account classification and normal balance

`ChartOfAccounts.php` contains the classification allowlist. `AccountingAccount` stores an arbitrary `debit`/`credit`; the save action only checks that it is one of those values and that class matches type. The database has no enum/check constraint. The five types and COGS-as-Expense must remain.

Proposed technical approach after decisions:

- Define each approved class/type/normal-balance policy centrally enough to keep account-entry validation and reports consistent. Retain account-specific contra balances; do not apply a blanket “Assets Debit / Equity Credit” validator that rejects contra accounts.
- Add a dedicated contra-asset classification for 1510 and contra-equity classification for 3050 if the CPA approves that statement presentation. Include both in all report/period allowlists and render the contra balance correctly. The PDF explicitly labels dividends Contra-Equity and Debit.
- Decide if 4090 is netted into Sales or displayed as a separate contra-revenue line. The current Income Statement calculates revenue as credit minus debit, so a debit posting to a Sales-class account reduces net Sales, but no separate returns presentation exists.
- Preserve use of actual debit/credit postings for GL and Trial Balance. Reconcile the stored `normal_balance` behavior used for pre-cutover YTD values and close calculations; used-account normal-balance changes require a controlled approval/history decision because account model currently protects used code/type/classification but not normal balance (`AccountingAccount.php:14–26`).

### Special accounts

- **1510 Accumulated Depreciation:** technically representable as an Asset with credit balance; current Balance Sheet uses type-based signs and could show a negative asset. It lacks a dedicated contra-asset class/label. CPA must approve whether to net against PPE or display separately. No depreciation schedule/posting automation is verified.
- **3050 Dividends Declared:** PDF says if applicable, before production posting, Contra-Equity, Debit. It is technically represented as a negative Equity balance by current type-based balance-sheet sign, but has no contra-equity classification. Add classification/report support only if approved.
- **4090 Sales Returns and Allowances:** debit activity can reduce Sales totals; separate reporting requires a contra-revenue bucket and presentation. CPA decision required.
- **3040 Current-Year Profit or Loss:** PDF explicitly specifies Equity, Credit when profit/Debit when loss. Existing system already derives unclosed earnings as a separate Balance Sheet line and closes earnings into approved Retained Earnings (`FinancialStatements.php:263–268`; `FiscalYearClosingService.php:55–58`). Preserve the derivation and prevent duplicate equity. CPA/professor must decide whether code 3040 is a reporting-only representation of that computed amount or a posted ledger account; the latter conflicts with current derive-and-close treatment unless redesigned.
- **2040 VAT Payable:** technically addable as a Liability class, but existing Output VAT is a Liability and Input VAT is an Asset. Do not map the same POS Output VAT flow to both. CPA must specify whether 2040 is a net settlement liability, a distinct tax obligation, or redundant with Output VAT, and define reconciliation/payment posting. Keep POS VAT estimates separate from company ledger balances.
- **5030 Rework costs in inventory cost:** PDF explicitly says CPA determines treatment; do not automatically expense or capitalize. Existing valued inventory supports approved value evidence and consumed-cost expense allocation, but receipt attachments alone do not post or revalue inventory. Preserve atomic valued-stock and GL posting. CPA must approve which rework amounts enter carrying value and how consumed portions reach COGS/expense; do not add an automatic receipt-to-journal path.
- **5090 Inventory Adjustments / Shrinkage:** PDF says subject to approval. Existing adjustment counterpart mapping permits approved Expense counterparts and inventory adjustments; do not assume every decrease is shrinkage or post it to COGS/Miscellaneous Expense.

### Miscellaneous Expense restrictions (PDF Section 4, pages 11–12)

The PDF limits 6190 to legitimate incidental business expenses that do not reasonably fit an existing expense account. It requires a clear transaction description and appropriate supporting reference/documentation; authorized review where applicable; use a specific existing expense account when appropriate; and prohibits automatic mapping of unmapped transactions to Miscellaneous Expense. It also says not to use it as a default for unidentified transactions, missing documentation, inventory losses, equipment purchases, or significant unusual expenses.

Account 6190 is explicitly blocked from posting by `AccountingAccount::isApprovedForPosting()`, regardless of active state, ordinary account approval, or mapping approval. This shared server-side eligibility boundary is used by manual journals, supplier purchase allocations and reconciliation, cash disbursements, valued stock movements and corrections, supplier purchase corrections, and purchase-VAT reclassification; UI filtering is not the enforcement boundary. No 6190 mapping fallback or transaction eligibility policy is introduced. Existing transaction atomicity and evidence handling remain unchanged. Keep the account unavailable until evidence, significance, and review policies are approved.

## 5. Functionality to remove, if justified

No removal is justified. Preserve current COA approval, account identity, historical account references, source mappings, posting workflows, and financial reports. No proposed account record was found in the inspected local database to delete. The sample record’s origin remains unverified.

## 6. CPA/professor approval decisions

Implementation blockers:

1. Final applicable account list and approved account-code/name reuse after review of FCDC source records and actual deployed account/mapping records.
2. Current/noncurrent and exact classifications for accruals, taxes, customer advances, loans, prepaid items, and other proposed accounts.
3. 1510 contra-asset presentation; 3050 Contra-Equity treatment; 4090 contra-revenue/net-sales presentation.
4. 3040 derived-versus-ledger-account treatment and profit/loss balance presentation.
5. Whether 2040 VAT Payable is distinct from 2030 Output VAT and how input/output balances settle.
6. Whether 5030 applies and approved rework capitalization/consumption treatment; whether 5090 applies and approved adjustment/shrinkage policy.
7. Applicability of each PDF-marked conditional account and 3020/3050 conditions before production posting.
8. Precise reviewer/approval requirement for Miscellaneous Expense transactions and acceptable supporting documentation.
9. Which POS/supplier/inventory categories require separate Sales, COGS or Inventory accounts. Existing mappings are single-destination, so any split requires explicit design approval.
10. Whether normal balance of used accounts is immutable or can be changed by controlled correction; preserve historical financial statement and YTD meaning.

## 7. File-by-file implementation plan

| File | Change only if approved | Reason / preservation requirement |
|---|---|---|
| `app/Livewire/Accounting/ChartOfAccounts.php` | Extend allowed classes; validate approved type/class/normal-balance combinations; keep unique code and approval flow. | Current enum-like lists are hardcoded; preserve five types and COGS as Expense. |
| `app/Models/AccountingAccount.php` | Add model-level constraints/used normal-balance protection only if policy requires. | Livewire-only checks are bypassable; preserve approval reset and used-account protections. |
| `app/Models/AccountingPostingMapping.php` | Change only if CPA-approved account split needs multiple/category-aware sources. | Current mapping sources/classes are explicit and single-account per source. |
| `app/Livewire/Accounting/FinancialStatements.php` | Add approved contra classes, returns presentation, and/or approved 3040 presentation. | Classification allowlists and match buckets are hardcoded; preserve derived unclosed earnings and equation checks. |
| `app/Livewire/Accounting/AccountingOverview.php` | Extend income/position class lists if new classes are approved. | Overview hardcodes classification groups; prevent accounts from disappearing or misbucketed. |
| `app/Services/Accounting/AccountingPeriodService.php` | Extend supported statement classes and closing readiness logic if new classes are approved. | Period-close reconciliation has its own allowlists; retain blockers and exact balance checks. |
| `app/Services/Accounting/FiscalYearClosingService.php` | Update income class set or treatment of approved contra-revenue if needed. | Preserve exactly-once close/reversal and retained-earnings transfer. |
| `app/Services/Accounting/ManualJournalService.php` | Enforce 6190 purpose/evidence/review rules at server-side post validation. | Prevent unsupported manual postings; preserve balancing, period, approval and immutability rules. |
| `app/Services/Accounting/SupplierPurchaseService.php` | Apply Miscellaneous restriction to non-inventory allocation and evidence when policy applies. | Preserve supplier invoice, inventory valuation, AP and atomic posting behavior. |
| `app/Services/Accounting/CashDisbursementService.php` | Apply restrictions to direct allocations and any review state. | Preserve AP settlement and cash/bank accounting; do not debit disallowed 6190 purpose. |
| `app/Livewire/Accounting/CashDisbursements.php` and relevant Blade view | Capture/display support or review status only if existing fields are insufficient. | Keep UI consistent with server-side policy; do not treat UI hiding as enforcement. |
| `app/Services/Inventory/RecordValuedStockMovement.php` | Reject Miscellaneous Expense for loss/shrinkage; handle approved counterpart class. | PDF explicitly excludes inventory losses from 6190; preserve valued movement/GL atomicity. |
| `app/Services/Inventory/CorrectValuedStockMovement.php` | Apply corresponding consumed-cost restriction only if applicable. | Preserve forward chronology, carrying-value, and correction constraints. |
| `app/Services/Accounting/InterveningSourceReconciliation.php` | Ensure recovered historical allocations cannot silently fall back to 6190 or duplicate postings. | Preserve source identity and exactly-once reconciliation. |
| `app/Services/Accounting/SupplierPurchaseCorrectionService.php` | Keep approved consumed cost and correction allocation behavior; enforce any adopted 6190 prohibition. | Preserve correction chain, original records, remaining/consumed cost allocations. |
| `app/Services/Accounting/SupplierPurchaseVatReclassificationService.php` | Extend class eligibility only for CPA-approved contra/new classes. | Preserve source-linked VAT reclassification and carrying value/AP invariants. |
| `app/Services/Accounting/OpeningBooksService.php` | Update class allowlists only if approved accounts enter opening/YTD schedules. | Preserve cutover controls and approved balance schedules. |
| `database/migrations/*accounting_accounts*` | No migration for account rows is assumed. Add a migration only if approved new persisted policy/class fields are necessary. | Preserve all existing account/journal history; no migrations before decisions. |
| `tests/Feature/ChartOfAccountsTest.php` | Assert approved classification/normal rules, code uniqueness, reapproval, and used-account guard. | Preserve current permission and account maintenance coverage. |
| `tests/Feature/FinancialStatementsTest.php`, `TrialBalanceTest.php`, `GeneralLedgerTest.php`, `AccountingPeriodsTest.php`, `FiscalYearClosingTest.php` | Assert contra signs/display, returns net/presentation, dynamic earnings and close no-double-count. | Verify existing report invariants and history. |
| `tests/Feature/JournalEntryTest.php` | Add 6190 allowed/disallowed cases, evidence/review and server permission behavior. | A UI-only test is insufficient; exercise actual posting service. |
| `tests/Feature/SupplierPurchaseInvoicesTest.php`, `CashDisbursementsTest.php`, `ValuedStockMovementTest.php`, `RecoveredMaterialTest.php`, `InterveningSourceReconciliationTest.php` | Assert no fallback to 6190 for prohibited categories and approved rework/adjustment behavior. | Preserve source-linked atomic postings and history. |
| `tests/Feature/PosSaleAccountingTest.php`, `ProductionAccountingActivationTest.php`, `AccountsPayableTest.php` | Verify mapping classification and any approved one-vs-many category mapping behavior. | Preserve POS/AP/activation requirements and exactly-once postings. |

No specific new migration or account seeder is authorized by this plan.

## 8. Required automated tests

Use existing feature tests; add behavior-level cases for decisions, not tests of wiring or copy. No tests were run during this read-only review.

1. **COA validation:** all approved type/class pairs; normal-balance rules for ordinary and contra classes; duplicate code rejection; approval reset when relevant fields change; used account identity/history protection.
2. **Miscellaneous Expense:** permit a legitimate incidental expense with clear description and supporting reference/document; reject an identifiable existing expense category routed to 6190; reject missing description/evidence; reject inventory loss, equipment purchase, significant/unusual expense and unidentified/default transactions; verify authorized review according to approved rule. Exercise service calls, not only UI selection.
3. **Contra reports:** credit accumulated depreciation reduces asset presentation; debit Dividends Declared reduces equity; debit Sales Returns reduces/net-presents Sales as approved; total equation and debit/credit Trial Balance remain exact.
4. **Current earnings:** profit and loss directions; Balance Sheet unclosed earnings not duplicated by 3040; fiscal-year close moves earnings once and reversals/reclose remain correct.
5. **VAT:** Output VAT mapping remains singular; Input VAT stays separately classified; 2040 behavior reconciles under selected approved policy; do not mix POS-only tax reports with ledger VAT balances.
6. **Rework / inventory:** receipt alone does not post or revalue; only approved, supported rework policy changes carrying value; consumed amount posts to selected approved expense/COGS; retain inventory quantity/value and original COGS correction invariants.
7. **Source integration:** POS, inventory movements, AP purchases/settlements, and cash disbursements use approved mappings exactly once; missing/unapproved mapping blocks source completion atomically.
8. **Historical reports:** inactive historical accounts remain visible in GL/TB; account renaming/classification changes do not silently rewrite past identification; opening/YTD and monthly/year-close reports remain consistent.

## 9. Implementation risks and dependencies

- **Blocking source interpretation:** Although the PDF is now available, its page 9–11 table extraction is layout-corrupted in places. The account names/conditional tags and clearly visible equity rules were read, but the professor/CPA should confirm exact presentation, especially 3050 contra-equity and the condition tied to production posting. Do not infer unresolved layout text as a finalized policy.
- **No production data:** local `project` database has only one unapproved sample account and one unapproved mapping. No live chart/mapping equivalence, transactions, or foreign references can be established from it.
- **Code single mappings:** splitting revenue, COGS, or inventory among multiple proposed accounts cannot be achieved by adding account rows alone; existing source mapping is one account per source. Requires a separate approved category-aware routing design or use of a shared account.
- **Reports use hardcoded lists:** adding classes requires coordinated updates to statement, overview, accounting period readiness, close, and relevant account selectors. Partial rollout can make reports unavailable or omit balances.
- **Miscellaneous controls span posting paths:** enforcing only the manual journal UI leaves supplier purchase, disbursement, inventory adjustment, or source reconciliation paths open. Conversely, overly broad rules could block valid incidental spend. Definition of “significant,” evidence sufficiency, and reviewer applicability need CPA/professor decisions.
- **Historical identification:** used account code/type/classification are protected, but names/descriptions are editable; workflow contract requires preserving historical identification. Do not rename used production accounts without a history display/snapshot plan.
- **Approval process:** account approval does not equal per-transaction Miscellaneous review. Role, evidence, and audit expectations must be resolved before production posting.
- **Inventory cost policy:** uploaded rework receipts alone must not alter value or post journals. Valuation requires separately approved cost treatment and atomic stock/GL processing.

## 10. Final implementation readiness assessment

**Not ready to implement the complete COA or enable production posting.** The PDF has been located and reviewed; it explicitly requires proposed-list validation and describes conditional accounts and Miscellaneous Expense restrictions. The specific account names, proposed codes, and proposal conditions are now available, but the following block account creation/activation: CPA/professor approval of actual applicability, special account treatment, Miscellaneous Expense review rule, production database reconciliation, and mapping assignment.

**Ready for a bounded implementation only after approvals:** preserve current account/mapping workflows; implement agreed classification/normal-balance validation; add Miscellaneous Expense server-side safeguards; update report class handling; and create/reuse only approved accounts. Then verify existing module paths and account-level integration before production activation. Do not seed all 54 accounts.
