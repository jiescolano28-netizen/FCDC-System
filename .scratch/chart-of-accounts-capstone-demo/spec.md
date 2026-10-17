# FCDC AIS: Isolated Capstone/Demo Chart of Accounts

Status: ready-for-agent

## Problem Statement

The capstone application needs to demonstrate FCDC's complete proposed Chart of Accounts without treating illustrative accounts as approved business records or affecting actual FCDC books. The existing Laravel application already persists accounts, maintains source-to-account mappings, authorizes account and mapping approvals, and posts source-linked accounting entries. Adding account records alone does not establish safe posting eligibility, approved accounting policies, or category-aware source routing.

Section 4 of the supplied FCDC requirements PDF proposes 54 accounts across seven presentation categories. Several are conditional, contra accounts need correct financial-statement signs, Current-Year Profit or Loss overlaps an existing derived calculation, and Miscellaneous Expense has restrictions that ordinary account approval does not enforce. An indiscriminate seeder, blanket normal-balance rule, automatic approval, or fallback mapping could corrupt or misrepresent financial results.

The earlier investigations verified the maintenance architecture and identified these gaps. Their database observations were limited to a workstation database, not production. The capstone now explicitly requires all 54 proposed accounts in an isolated demonstration database, with unresolved accounts safely unavailable for posting rather than omitted or assigned invented accounting policies.

## Solution

Provide an explicitly invoked, fail-closed demo setup and idempotent account seeder that makes the PDF's complete proposed chart visible through the existing COA maintenance page. Preserve actual business databases, account identities, transaction references, approval history, authorization, source mappings, and existing financial calculations.

Default all newly seeded accounts to inactive and unapproved. Permit later use only through existing authorized maintenance and approval actions, subject to applicable accounting-policy restrictions. Keep unresolved accounts unavailable for posting and identify the decision needed to release each restriction. Seeding creates account definitions only: no opening balances, sample transactions, journals, approvals, mappings, or financial results.

Display the exact notice **“Illustrative / Sample Data — Not Actual FCDC Financial Records”** only when the application's effective database is verified as the isolated demo target. Reuse existing forms, filters, approval actions, and mapping controls rather than redesigning the module.

Verify the implementation using the existing Laravel feature-test layer and command-level seeding tests in disposable databases. Deliver an evidence-based report distinguishing implemented behavior, executed verification, accounting-policy blockers, and unverified integrations.

## User Stories

1. As a capstone administrator, I want a dedicated demonstration database, so that illustrative accounts cannot affect actual FCDC books.
2. As a capstone administrator, I want existing isolation infrastructure reused when it is suitable, so that demonstration setup follows project conventions.
3. As a capstone administrator, I want the persistent demo database distinguished from an ephemeral test database, so that the chart remains available between demonstrations.
4. As a capstone administrator, I want explicit demo enablement and an allowed nonproduction environment, so that accidental seeding is rejected.
5. As a business data custodian, I want the effective database identity verified independently of its connection name or UI label, so that a mislabeled production connection cannot receive demo records.
6. As a business data custodian, I want ambiguous connections rejected before any writes, so that unsafe configuration fails closed.
7. As a business data custodian, I want current accounts, mappings, and transactions preserved, so that implementation cannot destroy or rewrite business history.
8. As a capstone administrator, I want demo seeding invoked separately from ordinary application seeding, so that routine deployment cannot install illustrative accounts.
9. As a demonstrator, I want all 54 PDF-proposed accounts visible, so that the presentation covers the complete reference chart.
10. As a professor, I want the seven PDF categories and their account counts preserved, so that completeness is independently checkable.
11. As an accounting reviewer, I want exact account codes and source account names, so that generic templates cannot substitute for the FCDC proposal.
12. As an accounting reviewer, I want conditional qualifications preserved with the account definitions, so that inclusion is not mistaken for confirmed applicability.
13. As an administrator, I want missing accounts created and exact matches identified, so that repeated setup is predictable.
14. As a business data custodian, I want code and definition conflicts reported without overwriting existing records, so that seeded definitions cannot silently replace another account.
15. As an administrator, I want failed seeding to leave no partial chart, so that the database remains in a known state.
16. As an accounting reviewer, I want repeated seeding to preserve actual approvals and account history, so that initialization cannot erase authorized decisions.
17. As an accounting reviewer, I want every newly seeded account inactive and unapproved, so that a proposed chart is not automatically usable in the books.
18. As an auditor, I want no fabricated approver or approval timestamp, so that approval metadata identifies real authorized actions.
19. As a COA maintainer, I want existing account creation and editing preserved, so that the demo remains representative of the working application.
20. As a COA maintainer, I want unique account codes enforced, so that journals and mappings cannot identify conflicting accounts.
21. As a COA maintainer, I want valid type and statement-classification combinations, so that reports place accounts consistently.
22. As an accounting reviewer, I want the five existing account types preserved, so that COGS does not become an incompatible sixth type.
23. As a COA maintainer, I want ordinary normal-balance validation and legitimate contra exceptions, so that both ordinary accounts and contra accounts can be represented correctly.
24. As an accounting reviewer, I want relevant account changes to require renewed approval, so that approval cannot survive an altered accounting definition.
25. As a business data custodian, I want used account identities and transaction references protected, so that maintenance cannot rewrite historical meaning.
26. As a COA viewer, I want inactive accounts included in the default listing and available filters, so that all proposed accounts remain inspectable.
27. As a COA viewer, I want active status, approval status, and posting eligibility distinguished, so that an approved but inactive account is not mistaken for a usable one.
28. As an authorized reviewer, I want account and mapping approvals to remain separate, so that approving an account cannot implicitly approve a source mapping.
29. As an auditor, I want approval events attributed to actual authorized employees, so that demo workflow evidence is traceable.
30. As an unauthorized employee, I want server-side rejection of maintenance and approval attempts, so that hiding controls is not the only protection.
31. As a financial-statement reader, I want credit-balance Accumulated Depreciation to reduce assets, so that total assets are not overstated.
32. As an accounting reviewer, I want Accumulated Depreciation unavailable for posting until presentation is confirmed, so that a proposed account does not authorize depreciation policy.
33. As a financial-statement reader, I want debit-balance Dividends Declared to reduce equity in an approved scenario, so that distributions cannot inflate equity.
34. As an accounting reviewer, I want conditional dividends blocked pending applicability and policy confirmation, so that corporate treatment is not assumed.
35. As a financial-statement reader, I want Sales Returns and Allowances to reduce net revenue, so that returned sales cannot increase income.
36. As a financial-statement reader, I want derived unclosed earnings counted once, so that account 3040 cannot duplicate Current-Year Profit or Loss.
37. As a COA viewer, I want account 3040 visible but unavailable for posting by default, so that the complete proposal is shown without redesigning earnings accounting.
38. As a VAT reviewer, I want VAT Payable present without duplicating Output VAT postings, so that one taxable sale does not create two liabilities for the same tax.
39. As a VAT reviewer, I want POS-only VAT payable estimates distinguished from company ledger liabilities, so that illustrative reporting does not claim a final company tax determination.
40. As an inventory accountant, I want the proposed rework account visible but blocked pending policy, so that the chart is complete without automatically capitalizing or expensing rework.
41. As an inventory employee, I want receipt uploads to remain supporting evidence only, so that uploading a document cannot silently revalue stock or create a journal.
42. As an inventory accountant, I want stock adjustments distinguished from approved shrinkage, so that every inventory difference is not treated as a loss.
43. As an inventory accountant, I want inventory losses rejected from Miscellaneous Expense, so that stock differences use their approved treatment.
44. As an accountant, I want Miscellaneous Expense limited to legitimate incidental business expenses with no appropriate specific account, so that it cannot become a balancing plug.
45. As an accountant, I want clear descriptions and appropriate supporting references or documents required for Miscellaneous Expense, so that the transaction has reviewable evidence.
46. As an accounting reviewer, I want unidentified, unsupported, equipment, significant unusual, and more-specifically-classifiable expenses excluded from Miscellaneous Expense, so that accounting nature is not concealed.
47. As an accounting reviewer, I want unresolved Miscellaneous Expense review rules to block posting, so that account approval cannot substitute for transaction review.
48. As an accountant, I want the Miscellaneous Expense restrictions enforced through every relevant server-side posting path, so that direct service calls cannot bypass the UI.
49. As a source-module operator, I want missing or unapproved mappings to block completion, so that failed routing never falls back to Miscellaneous Expense.
50. As a POS operator, I want existing approved source mappings and atomic sale posting preserved, so that adding accounts does not change working checkout behavior.
51. As an accounting reviewer, I want separate Sales, COGS, and Inventory proposals visible without implied category routing, so that additional account rows are not mistaken for a mapping redesign.
52. As a capstone administrator, I want supporting accounts reported separately from the 54 proposals, so that card settlement receivable or clearing cannot silently change the reference-chart total.
53. As an AP accountant, I want supplier purchases and AP settlements to retain their original accounting roles, so that payments do not recognize purchases twice.
54. As a disbursement accountant, I want direct disbursements and AP settlements to retain their existing approvals and source links, so that cash payments remain traceable.
55. As an inventory accountant, I want valued stock movements and their GL effects to remain atomic, so that quantities, carrying values, and ledger balances cannot diverge.
56. As an accountant, I want approved opening books and pre-cutover YTD rules preserved, so that demo accounts cannot manufacture historical coverage.
57. As an accounting administrator, I want closed-period and fiscal-year controls preserved, so that demonstration activity cannot bypass posting boundaries.
58. As an auditor, I want exactly-once source posting and immutable posted journals preserved, so that retries and corrections cannot duplicate or rewrite transactions.
59. As a report reader, I want posted journal, General Ledger, Trial Balance, and Financial Statement amounts to reconcile, so that chart changes preserve financial integrity.
60. As a report reader, I want drafts and unapproved entries excluded from posted balances, so that preparation activity cannot alter the books.
61. As a demonstrator, I want an unmistakable sample-data notice in the verified demo COA, so that viewers do not confuse illustrative accounts with actual financial records.
62. As a production user, I want demo records and demo labeling absent from production, so that actual business reporting remains separate and correctly described.
63. As a COA viewer, I want existing search, filters, maintenance actions, and mapping controls preserved, so that demonstrating the chart does not require learning a replacement interface.
64. As a professor, I want account approval distinguished from CPA certification, so that application status does not overstate professional validation.
65. As a QA engineer, I want actual executed isolation, maintenance, posting, and reconciliation tests, so that completion is based on observed behavior rather than source inspection.
66. As a QA engineer, I want failed and skipped tests reported separately from passes, so that incomplete verification cannot appear successful.
67. As a maintainer, I want implementation-introduced failures corrected and unrelated existing failures identified separately, so that regressions remain attributable.
68. As a project reviewer, I want a final account-by-account status table and phase-by-phase completion evidence, so that unresolved accounting decisions remain visible.
69. As an implementation agent, I want safe independent work to continue while conditional accounts remain blocked, so that an unresolved policy does not stop the complete demo chart from being prepared.
70. As a business data custodian, I want execution withheld when isolation cannot be verified, so that delivery pressure cannot justify seeding an unsafe database.

## Implementation Decisions

### 1. Scope, authority, and existing architecture

- Implement the isolated capstone/demo COA, not a production chart rollout and not another investigation-only deliverable. This specification is the implementation contract; producing this document does not itself implement the feature.
- Use the PDF's Section 4 for the account manifest and restrictions. Reuse the verified source findings from the investigation, gap analysis, and final reconciliation. The current request overrides the older recommendation not to seed all 54 only for an independently verified isolated demo database. It grants no production activation or accounting-policy approval.
- Reuse the existing `accounting_accounts` data model, `AccountingAccount`, COA Livewire component, account approvals, mapping approvals, and posting eligibility mechanism. Do not introduce a parallel account store, replace the accounting architecture, or treat persisted demo accounts as the glossary's nonpersisted Reference accounts.
- Preserve the ADRs governing approved accrual cutover, atomic immutable source-linked posting, control-account schedules, and forward-only moving weighted-average inventory valuation. Existing authorized self-posting is not replaced with a universal maker-checker requirement.
- No schema migration is assumed merely to add accounts. Reuse existing fields and supported statement classifications. Introduce persisted policy metadata or additional classifications only when needed for an approved rule, with every affected reporting and posting consumer updated together; never introduce a sixth account type.
- Generate any new Laravel classes, seeders, commands, or migrations using `php artisan make:*`. All Artisan operations use `php artisan`.

### 2. Environment isolation and execution safeguards

- Inspect effective environment and connection configuration without changing the working business connection or revealing credentials. Reuse an existing persistent demo target only if its isolation can be verified. Otherwise provide a dedicated named demo connection using the project's standard configuration conventions and separately provisioned nonproduction resources.
- Existing PHPUnit configuration forces the `testing` environment, SQLite `:memory:`, and the activity-log connection onto SQLite. Reuse it for automated tests, but do not claim it is a persistent capstone database. Configuration inspection found no explicit demo mode or dedicated demo connection; this is not proof that no externally provisioned demo database exists.
- Require all of: explicit demo enablement; an explicit nonproduction environment allowlist; a dedicated or independently verified isolated target; and agreement between intended and actual connected database identity. Production and unknown environments are rejected regardless of flags or command options.
- For a server database, verify the connected server/database identity against a separately established demo provisioning identity and use credentials restricted to the demo resource. A database name containing “demo,” a connection alias, an environment variable, or a UI label is not sufficient proof. Reject endpoints whose separation or privileges cannot be established.
- For persistent SQLite, verify the canonical file identity, dedicated demo location, and separation from business database files, including aliases or symbolic links. SQLite in-memory targets are allowed for explicitly isolated tests only, not presented as a persistent demo deployment.
- Resolve URL overrides, socket settings, cached configuration, connection aliases, and any read/write endpoints before verification. Reject inconsistent, shared, or ambiguous targets. Check the actual write connection, not merely the default connection's declared name.
- Ensure application models, transaction writes, and audit activity for the demo run use the isolated target. Do not seed into one connection while serving the COA or recording approval activity through a business connection. Keep any database-backed session, queue, or cache writes used during demonstration isolated as well.
- Perform safety checks before account writes and before demo migrations or setup operations. Do not truncate, refresh, reset, overwrite, or migrate a business database. Provide no force option that bypasses isolation checks.
- If independent isolation evidence is unavailable, prepare code, configuration guidance, and disposable-database tests, but do not execute demo seeding. Report the exact missing evidence or provisioning prerequisite.

### 3. Account manifest and safe initial state

- Seed exactly the 54 proposed codes in the manifest below. Preserve the PDF's base account names, punctuation, and category membership. The PDF's “if applicable,” “as applicable,” and “subject to approval” qualifications are account conditions, retained in account descriptions or existing suitable metadata rather than silently discarded or treated as approval.
- All newly created rows start inactive, with no approver, no approval timestamp, and no usage timestamp. Their initial posting eligibility is false. This deliberately conservative default also avoids making unused unapproved demo accounts active report-readiness blockers.
- Counts refer to the 54 proposed codes, not permission to delete unrelated pre-existing accounts to force the whole table to 54 rows. On an empty dedicated demo chart, there are exactly 54 rows after seeding. On a prepopulated isolated target, preserve unrelated rows and separately report them. The seeder itself adds no supporting accounts.
- Keep the five account types: Asset, Liability, Equity, Revenue, Expense. COGS remains a statement classification under Expense. The seven PDF categories are presentation groupings, not seven database account types; the final category contains both Revenue and Expense accounts.
- The following manifest uses existing statement-classification keys. Ordinary non-equity normal balances are accounting conventions for the demo, not a claim that the PDF supplies a normal-balance column for every account. Explicit equity rules and contra directions follow the PDF and the current prompt.
- Entries marked **provisional** are non-postable representations needed to store and display the proposal using the existing schema. They do not settle maturity, capitalization, reporting presentation, or applicability. Show their pending-treatment explanation; never release them by treating the stored placeholder as approved policy.

| PDF category | Code | Account name | Type | Statement classification | Normal balance | Condition or pending decision |
|---|---|---|---|---|---|---|
| Assets — 1000 | 1010 | Cash on Hand | Asset | `cash` | Debit | Ordinary proposed account |
| Assets — 1000 | 1020 | Cash in Bank | Asset | `bank` | Debit | Ordinary proposed account |
| Assets — 1000 | 1030 | Accounts Receivable | Asset | `accounts_receivable` | Debit | Preserve GL-only receivable scope |
| Assets — 1000 | 1040 | Input VAT | Asset | `input_vat` | Debit | No automatic purchase-VAT eligibility |
| Assets — 1000 | 1050 | Inventory — Recovered Construction Materials | Asset | `inventory` | Debit | No new category routing |
| Assets — 1000 | 1060 | Inventory — Other Resale Materials | Asset | `inventory` | Debit | No new category routing |
| Assets — 1000 | 1070 | Prepaid Expenses | Asset | `other_current_asset` | Debit | Validate actual treatment before use |
| Assets — 1000 | 1080 | Advances to Employees/Suppliers | Asset | `other_current_asset` | Debit | No supplier-advance application workflow |
| Assets — 1000 | 1500 | Property and Equipment | Asset | `equipment` | Debit | No asset-management expansion |
| Assets — 1000 | 1510 | Accumulated Depreciation | Asset | `contra_asset` | Credit | Dedicated contra-asset classification implemented; account remains non-postable pending CPA approval |
| Liabilities — 2000 | 2010 | Accounts Payable | Liability | `accounts_payable` | Credit | Preserve AP control-account rules |
| Liabilities — 2000 | 2020 | Accrued Expenses Payable | Liability | `other_current_liability` | Credit | Validate actual accrual treatment |
| Liabilities — 2000 | 2030 | Output VAT | Liability | `output_vat` | Credit | Existing singular POS VAT role |
| Liabilities — 2000 | 2040 | VAT Payable | Liability | `other_current_liability` | Credit | Provisional; distinct use and settlement policy pending |
| Liabilities — 2000 | 2050 | Salaries and Wages Payable | Liability | `other_current_liability` | Credit | Validate applicable payroll liability |
| Liabilities — 2000 | 2060 | Other Taxes Payable | Liability | `other_current_liability` | Credit | If applicable |
| Liabilities — 2000 | 2070 | Customer Advances | Liability | `other_current_liability` | Credit | If applicable; no new advance module |
| Liabilities — 2000 | 2080 | Loans Payable | Liability | `other_noncurrent_liability` | Credit | Provisional; if applicable; maturity classification pending actual terms |
| Equity — 3000 | 3010 | Capital Stock | Equity | `capital` | Credit | Validate corporate records |
| Equity — 3000 | 3020 | Additional Paid-in Capital | Equity | `other_equity` | Credit | If applicable |
| Equity — 3000 | 3030 | Retained Earnings | Equity | `retained_earnings` | Credit | Preserve existing fiscal-year close role |
| Equity — 3000 | 3040 | Current-Year Profit or Loss | Equity | `other_equity` | Credit | Provisional storage convention only; profit Credit/loss Debit; no ledger posting |
| Equity — 3000 | 3050 | Dividends Declared | Equity | `contra_equity` | Debit | Dedicated contra-equity classification implemented; account remains non-postable pending CPA approval |
| Revenue — 4000 | 4010 | Sales — Recovered Construction Materials | Revenue | `sales` | Credit | No new category routing |
| Revenue — 4000 | 4020 | Sales — Other Materials | Revenue | `sales` | Credit | No new category routing |
| Revenue — 4000 | 4030 | Demolition Service Revenue | Revenue | `sales` | Credit | Provisional operating-revenue grouping; if applicable |
| Revenue — 4000 | 4040 | Other Operating Revenue | Revenue | `sales` | Credit | Validate operating-revenue grouping before use |
| Revenue — 4000 | 4090 | Sales Returns and Allowances | Revenue | `contra_revenue` | Debit | Dedicated contra-revenue classification implemented; preserve existing net-sales calculation |
| Cost of Goods Sold — 5000 | 5010 | Cost of Goods Sold — Recovered Materials | Expense | `cost_of_goods_sold` | Debit | No new category routing |
| Cost of Goods Sold — 5000 | 5020 | Cost of Goods Sold — Other Materials | Expense | `cost_of_goods_sold` | Debit | No new category routing |
| Cost of Goods Sold — 5000 | 5030 | Approved Rework Costs Included in Inventory Cost | Expense | `cost_of_goods_sold` | Debit | Provisional display/storage only; if applicable; capitalization versus expense policy unresolved |
| Cost of Goods Sold — 5000 | 5090 | Inventory Adjustments / Shrinkage | Expense | `other_expense` | Debit | Subject to approval; not every adjustment is shrinkage |
| Operating Expenses — 6000 | 6010 | Salaries and Wages Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6020 | Rent Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6030 | Utilities Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6040 | Transportation and Delivery Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6050 | Repairs and Maintenance Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6060 | Fuel and Lubricants Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6070 | Tools and Supplies Expense | Expense | `operating_expense` | Debit | No automatic capitalization decision |
| Operating Expenses — 6000 | 6080 | Equipment Maintenance Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6090 | Communication and Internet Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6100 | Office Supplies Expense | Expense | `operating_expense` | Debit | No automatic capitalization decision |
| Operating Expenses — 6000 | 6110 | Depreciation Expense | Expense | `operating_expense` | Debit | No depreciation schedule or automation |
| Operating Expenses — 6000 | 6120 | Permits and Licenses Expense | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6130 | Professional Fees | Expense | `operating_expense` | Debit | Ordinary proposed account |
| Operating Expenses — 6000 | 6140 | Bank Charges | Expense | `operating_expense` | Debit | Demo operating-expense grouping; validate policy before use |
| Operating Expenses — 6000 | 6150 | Insurance Expense | Expense | `operating_expense` | Debit | No automatic prepaid-versus-incurred decision |
| Operating Expenses — 6000 | 6160 | Other Taxes and Fees Expense | Expense | `operating_expense` | Debit | As applicable; distinguish from VAT/payables |
| Operating Expenses — 6000 | 6190 | Miscellaneous Expense | Expense | `other_expense` | Debit | Restricted incidental use; posting blocked while review policy unresolved |
| Other Income and Expenses — 7000 | 7010 | Other Income | Revenue | `other_income` | Credit | Ordinary proposed account |
| Other Income and Expenses — 7000 | 7020 | Interest Income | Revenue | `other_income` | Credit | Ordinary proposed account |
| Other Income and Expenses — 7000 | 7030 | Interest Expense | Expense | `other_expense` | Debit | Ordinary proposed account |
| Other Income and Expenses — 7000 | 7040 | Loss on Disposal of Equipment | Expense | `other_expense` | Debit | If applicable; no disposal workflow added |
| Other Income and Expenses — 7000 | 7090 | Other Non-Operating Expenses | Expense | `other_expense` | Debit | Ordinary proposed account |

- Manifest acceptance totals are Assets 10, Liabilities 8, Equity 5, Revenue 5, COGS 4, Operating Expenses 17, and Other Income and Expenses 5: 54 distinct codes. Category membership is independent of database type.
- The PDF explicitly conditions 2060, 2070, 2080, 3020, 3050, 4030, 5030, 6160, and 7040. Account 5090 is separately subject to approval. In addition, retain pending-policy restrictions on 1510, 2040, 3040, and 6190, plus any unresolved classification or treatment identified during implementation.

### 4. Idempotent, non-destructive seeding contract

- Provide a dedicated, explicitly invoked demo account seeder or the equivalent established project mechanism. Do not register it in normal production seeding or run it automatically during application startup, migrations, installation, or deployment.
- Verify isolation first, then compare all proposed definitions before inserting missing rows. Compare exact code, name, type, classification, and normal balance. Treat conflicting definitions as conflicts, not as permission to update or relabel an existing account.
- Treat operational state independently from definition matching. An exact definition that an authorized user subsequently activated, approved, deactivated, or used must retain its state and history on a rerun. Never clear approvals, reactivate, deactivate, rename, or rewrite an existing row merely to restore seed defaults.
- An existing held account whose state would violate an unresolved policy is an explicit safety conflict, not an exact usable match. Report it without silently resetting its state or granting approval; posting restrictions must remain authoritative.
- Detect every conflict before applying the batch where practicable. Any conflict fails the batch with an actionable account-code/field report and leaves the chart unchanged. Create missing rows transactionally so a later insertion failure cannot leave a partial batch. Preserve database uniqueness as the final code-uniqueness boundary.
- Report created accounts, exact existing matches, conflicts, and preserved unrelated/supporting accounts separately. An unchanged rerun creates zero accounts and changes no existing account or transaction metadata.
- Never create sample journal entries, opening balances, POS transactions, inventory movements, supplier invoices, financial results, accounts outside the manifest, mappings, or approval metadata in this seeder. Isolated test fixtures are not demo-seeder output.

### 5. Classification, normal balances, and accounting-policy restrictions

- Make account type/classification/normal-balance rules consistent between account maintenance, model/business validation, posting eligibility, and reporting consumers. Prefer a small shared policy within existing accounting boundaries rather than duplicated matrices or a new general-purpose framework.
- Enforce ordinary expected normal balances without banning legitimate contra-asset Credit, contra-equity Debit, or contra-revenue Debit treatment. A normal balance describes expected treatment; it must not force actual ledger balances to be positive or forbid a legitimate opposite-direction posted balance.
- Do not identify accounting nature by searching account names. Use explicit account definitions and supported accounting classifications/policy. Preserve historical account IDs and do not automatically migrate or “correct” existing business account definitions.
- Preserve approval reset after relevant definition changes and reactivation, used code/type/classification immutability, used-account deletion protection, and inactive-account exclusion from new postings. A rerun of the seeder is not an account-maintenance edit.
- Do not silently change used-account normal balances or historical pre-cutover YTD meaning. If a correction would alter historical interpretation, report the need for an approved correction policy rather than rewriting the account through this demo rollout.
- Inactive plus unapproved is the initial state, not a substitute for policy enforcement. Normal approval or a direct posting-service call must not bypass an unresolved treatment restriction. Reuse existing server-side eligibility checks and add the smallest shared restriction needed where those checks are insufficient. Resolve restrictions only through an approved policy and an authorized workflow, never through seeding.
- **1510:** represent a contra-asset with expected Credit balance. In an isolated, explicitly approved test scenario its posted credit reduces net assets; never apply an absolute-value or normal-balance transformation that inflates assets. Keep the demo account inactive and unapproved pending presentation confirmation. No depreciation scheduling or automatic entries.
- **3050:** retain Equity as its fundamental type and Contra-Equity as its accounting meaning, with Debit normal balance. In an approved test scenario its debit reduces equity. The dedicated `contra_equity` classification is now implemented while presentation/applicability remain pending CPA approval. No automatic dividends entries.
- **4090:** represent contra-revenue with Debit normal balance. Preserve the existing credit-minus-debit net-revenue treatment, so a debit reduces sales. Do not add a separate returns presentation or category-routing policy without approval.
- **3040:** include the account but prohibit ledger posting under this specification. Preserve derived unclosed earnings and the existing transfer to retained earnings at fiscal-year close. Do not create a journal to 3040, add a second earnings figure, or subtract earnings with a balancing plug. Its stored Credit default is not a substitute for the PDF's profit-Credit/loss-Debit rule.
- **2040:** include the account inactive and unapproved. Do not map the same POS Output VAT to both 2030 and 2040, infer net VAT settlement, or conflate a POS-only VAT payable estimate with the company's final tax liability.
- **5030:** include a clearly marked non-postable provisional account. Its PDF placement under COGS and temporary Expense/Debit fields do not authorize expensing or capitalization. Do not change inventory valuation, consumed cost, or journals from a receipt upload, and do not add rework posting behavior.
- **5090:** preserve account approval and transaction-purpose approval requirements. Do not classify every stock adjustment as shrinkage, redirect differences into Miscellaneous Expense, or change existing atomic stock/GL behavior.

### 6. Miscellaneous Expense safeguards

- Account 6190 is only for legitimate incidental business expenses that do not reasonably fit an existing specific expense account. Never use it as an unmapped-source fallback, error-recovery account, balancing plug, or destination for unidentified activity.
- Reject or defer transactions lacking a clear description or appropriate supporting reference/documentation. Reuse real existing narration, reference, evidence, approval, and audit fields; do not invent evidence or reviewer identity.
- Reject inventory losses, equipment purchases, unsupported transactions, significant or unusual expenses requiring specific treatment, and expenses belonging to an existing more appropriate account. Do not invent a monetary threshold, infer significance from an arbitrary amount, or attempt to establish accounting nature by keyword matching alone.
- The current request supplies no complete approved definition of evidence sufficiency, significance, or when separate review is required. Therefore keep 6190 unavailable for posting until that policy is approved. Ordinary account/mapping approval and an authorized poster alone do not resolve this restriction.
- Enforce the fail-closed restriction server-side for manual journals; supplier purchase allocations; historical-source reconciliation; cash disbursement allocations; valued stock movements and corrections; supplier purchase corrections; and purchase-VAT reclassification. Verify any additional reachable path discovered while following these existing services.
- Use a shared existing eligibility/business-policy boundary where suitable. Do not copy a separate 6190 validator into each UI or service. Preserve each service's existing permission, balancing, period, source-reference, and transaction-atomicity checks.
- A later approved release of 6190 must enforce the applicable purpose, evidence, specific-account selection, and review rules through those same server-side paths. This specification does not authorize inventing that missing policy to demonstrate a successful posting.

### 7. Source mappings and integration compatibility

- Preserve the existing approved source-to-account mechanism, including separate account and mapping approval, source references, exactly-once posting, permission checks, historical references, and atomic failure when mappings are missing or unapproved.
- Do not create or approve mappings in the demo account seeder. End-to-end demonstration posting, when required and otherwise ready, uses actual authorized employees and existing approval actions in the verified isolated environment. Record genuine approval events.
- One source normally resolves to one account. Adding 4010/4020, 5010/5020, or 1050/1060 does not introduce recovered-versus-other category routing. Preserve single-destination behavior and document the separate decision needed for each split Sales, COGS, or Inventory operation.
- Keep additional proposed accounts available for maintenance without forcing them into automatic posting. Missing mappings must continue blocking source completion, not select another proposed account or Miscellaneous Expense.
- Card settlement receivable/clearing is an existing supported operational role absent from the PDF's 54 proposals. If required for an approved card-POS demonstration, report it as a separate supporting-account prerequisite; do not silently add a 55th account or reuse Cash, Bank, or generic AR without approval.
- Verify Journal Entry, General Ledger, Trial Balance, Financial Statements, POS, Inventory, Accounts Payable, Supplier Purchases, Cash Disbursements, VAT Compliance, Opening Books, Accounting Periods, and Fiscal-Year Closing. Change each only when required to enforce this contract; do not rewrite modules merely because they are on the verification list.
- Preserve AP and Inventory control-account schedules, source-linked corrections and reversals, approved cutover/opening coverage, closed-period restrictions, fiscal-year closing behavior, frozen original sale COGS, and forward-only valuation chronology.

### 8. Demo COA interface

- Use the existing Livewire page, account form, search, account-type/status filters, account approval actions, and source-mapping controls. The current listing already includes code, name, description, type, classification, normal balance, active status, and approval status; preserve those fields rather than rebuilding their presentation.
- Display the exact sample-data notice only for a verified demo runtime connected to the isolated data store. A flag without verified isolation is insufficient. Do not display the notice on actual production records, even if a demo flag is mistakenly enabled.
- Replace production-only wording on this page with context-appropriate wording when running the verified demo; preserve production wording outside that context. Do not describe the persisted demonstration chart as the approved production chart.
- The default unfiltered listing must expose all 54 proposals, including inactive rows. Existing filters may narrow results intentionally. Show clear pending-approval and inactive states, explain that inactive accounts cannot receive new postings, and expose the pending-treatment reason through existing suitable account details.
- Keep administrative controls permission-gated and enforce every action server-side. Do not add sidebar modules, unrelated components, a replacement approval system, or language implying account approval is CPA certification.

### 9. Incremental execution and acceptance gates

| Phase | Implementation boundary | Completion evidence |
|---|---|---|
| 1. Environment isolation and preparation | Inspect current configuration; establish or reuse a verified isolated persistent demo target; implement fail-closed safeguards | Effective connection identity and isolation evidence recorded; unsafe targets rejected without writes; no business configuration or records changed |
| 2. Complete proposed chart | Explicit demo seeder; complete manifest; transactional conflict handling; inactive/unapproved defaults | 54 distinct proposal codes on a clean isolated target; unchanged rerun; conflicts leave records unchanged; no seeded transactions, mappings, or approval metadata |
| 3. Classification and validation | Five types; consistent normal-balance rules with contra support; safe special-account restrictions | Account validation and approved fixture-based signs reconcile; unresolved accounts remain non-postable; earnings and VAT are not duplicated |
| 4. Miscellaneous Expense safeguards | Shared authoritative server-side restrictions across all reachable posting paths | Prohibited and unsupported 6190 postings rejected; unresolved review policy blocks use even if ordinary account approval is present |
| 5. Mappings and integrations | Existing source mappings, authorization, atomicity, and exactly-once behavior preserved | Relevant integration suites executed; missing/unapproved mappings block completion; split-routing and supporting-account prerequisites documented separately |
| 6. Demo interface | Minimal notice/context wording; complete listing and existing controls retained | Actual verified demo page observed; all required fields and inactive proposals inspectable; non-demo control does not show demo labeling |
| 7. Automated testing | Focused behavior coverage and relevant existing regression suites on disposable databases | Actual commands and pass/fail/skip counts recorded; implementation regressions fixed; unrelated failures identified separately |
| 8. Verification and delivery | Evidence-based final report with account and phase status | All ten required report sections delivered; every phase classified honestly; unresolved approvals and unverified behavior remain explicit |

- Follow phase order for dependent work, inspect only relevant dependencies, make the smallest necessary changes, execute focused verification, correct introduced failures, and record remaining risks before proceeding.
- Continue independent work while policy-held accounts remain inactive and non-postable. Stop only the affected execution path when isolation, data integrity, destructive action, or an unapproved accounting policy would be at risk.
- Do not run destructive setup or tests against business databases. Database provisioning or actions that could affect unrelated records require separate authorization; this contract supplies none.

## Testing Decisions

### Confirmed seams and test quality

- The user confirmed the existing Laravel feature-test seams: authenticated Livewire actions and public posting-service entry points, with assertions on persisted accounts, approval activity, journal effects, and report results; plus command-level demo-seeder isolation tests using disposable databases.
- Prefer this existing application-level integration layer over new lower-level interfaces. Invoke the same actions and public services used by application consumers. Do not introduce repositories, mocked posting pipelines, permanent browser automation, or test-only accounting APIs merely to make assertions easier.
- The extra command seam is necessary because environment refusal and database isolation happen before UI interactions. Exercise the real explicitly invoked seeder through its command entry point rather than testing only a private guard helper.
- Good tests observe consumer-visible behavior, financial amounts, rejection outcomes, record preservation, permissions, and transaction atomicity. Avoid tests of internal method calls, copied implementation lists, wording unrelated to the explicit notice requirement, incidental defaults, or mock echoes.
- Independently verify the full expected code/name/category manifest against the PDF, not merely “54 rows” or a comparison against the production seeder's own returned array. The exact notice is a user-visible requirement, but source-code text searches are not UI verification.
- Reuse existing feature-test conventions, disposable SQLite setup, `RefreshDatabase` where appropriate, and permission helpers. Never point a refreshing test at a persistent demo or business database. Use disposable server-database checks where the selected persistent driver has identity or transaction behavior not demonstrated by SQLite.
- No permanent browser automation is requested. During implementation, observe the actual rendered demo COA and a non-demo control using a browser, with a real authorized session and the verified target, then close the session. If no runnable UI exists, report the visual-verification limit rather than treating component tests as visual proof.

### A. Isolation and seeding

- Execute the seeder against an explicitly enabled, verified disposable target and assert all expected definitions and inactive/unapproved/non-postable initial states. Approval actor, approval time, and usage metadata remain absent.
- Reject production, disallowed/unknown environment, disabled demo mode, ordinary business connection, misleading connection alias, mismatched connected identity, unsafe URL/socket override, shared target, and unverified isolation. Assert no application or audit writes on rejected runs.
- Use independent disposable business and demo databases to prove demo writes remain confined to the demo target and do not enter business COA queries or reports. Do not inspect or write actual production to prove separation.
- Assert exactly 54 distinct expected proposal codes and category totals on a clean target. Preserve and separately report unrelated accounts on a populated isolated target.
- Rerun after initial seeding and after legitimate authorized state changes to ordinary accounts. Assert unchanged IDs, definitions, operational state, approval metadata, timestamps, mappings, journal references, and transaction amounts.
- Exercise conflicting names, types, classifications, and normal balances under an existing proposed code. Assert an actionable conflict and no batch mutations. Exercise a failed insertion to prove transaction rollback. Assert a pending-policy state conflict is not silently normalized.
- Assert ordinary production seeding does not invoke the demo chart and creates no proposal accounts implicitly.
- Prior art: `DatabaseIsolationTest` for enforced SQLite isolation; `ChartOfAccountsTest` for persisted account behavior and permissions. Add a focused demo-seeding feature suite because the explicit seeding command does not yet exist.

### B. COA maintenance and authorization

- Verify account creation and editing, unique-code rejection, allowed and forbidden type/classification pairs, ordinary normal balances, explicit contra exceptions, and rejection of unsupported normal-balance choices through both maintenance and authoritative business validation.
- Verify activation/deactivation, genuine authorized approval, reset after relevant edits/reactivation, used-account identity and deletion protection, preservation of historical references, and rejection of unauthorized direct actions.
- Verify all proposed accounts are discoverable with the default filters, inactive filtering still works, and status does not imply posting eligibility. Test demo/non-demo presentation in their respective isolated application contexts.
- Prior art: `ChartOfAccountsTest`, `ModulePermissionTest`, and `AuthorizedAccountingWorkflowTest`. Extend behavioral coverage rather than preserving incidental wording assertions or adding UI-only authorization checks.

### C. Special accounting behavior

- In isolated approved test scenarios, post balanced fixture transactions proving that 1510 reduces assets, 3050 reduces equity, and 4090 reduces net revenue. These fixtures demonstrate arithmetic and report behavior, not authorization to activate those accounts in the persistent demo.
- Verify derived earnings for both profit and loss, inactive 3040's absence from posting eligibility, and no second earnings inclusion or automatic 3040 journal. Cover fiscal-year close and supported reversal/reclose behavior.
- Verify one POS Output VAT liability posting and no automatic 2040 mapping, duplicate liability, or inferred VAT settlement. Keep POS-sourced tax results distinct from company ledger balances.
- Upload a receipt through the existing authorized surface and assert unchanged inventory carrying value and journal effects, not just successful upload.
- Verify stock adjustment purposes remain distinct and inventory differences cannot reach 6190. Verify blocked rework treatment does not alter quantities, carrying values, or posted journals.
- Prior art: `FinancialStatementsTest` for exact signs, derived earnings, cutover coverage, and inactive historical accounts; `FiscalYearClosingTest`; `VatRecordsTest`, `VatSummaryTest`, and `VatReturnPreparationTest`; `InventoryReceiptAttachmentTest`; `ValuedStockMovementTest`; and `RecoveredMaterialTest`.

### D. Miscellaneous Expense enforcement

- Exercise manual journals, supplier purchase allocations, source reconciliation, direct disbursements, inventory movements/corrections, supplier purchase corrections, and purchase-VAT reclassification through their real server-side entry points.
- While review policy is unresolved, assert 6190 is rejected even when test setup makes it active and ordinarily approved. This proves the restriction is not only a hidden option or initial inactive state.
- Assert failures preserve the source, journal, stock, AP, cash, and audit state appropriate to that operation's atomic boundary. Verify no failed mapping or unclassified amount substitutes 6190 automatically.
- Use unrelated approved accounts as controls to prove the safeguard does not disable valid existing posting. Do not fabricate a successful 6190-policy approval to claim that unresolved allowed-use behavior is complete.
- If an actual approved review policy becomes available during implementation, add its legitimate incidental-expense success case plus missing-description, missing-evidence, missing-review, wrong-purpose, specific-account-available, equipment, inventory-loss, and significant/unusual rejection cases. Otherwise document that permitted-use activation remains blocked.
- Prior art: `JournalEntryTest`, `SupplierPurchaseInvoicesTest`, `CashDisbursementsTest`, `InterveningSourceReconciliationTest`, `ValuedStockMovementTest`, and existing supplier correction/VAT reclassification cases in their current suites.

### E. Posting integrations and financial reconciliation

- Execute relevant existing coverage for Journal Entry, General Ledger, Trial Balance, Financial Statements, POS accounting, Accounts Payable, Supplier Purchases, Cash Disbursements, Inventory, VAT Compliance, Opening Books, Accounting Periods, Fiscal-Year Closing, approval, and permissions.
- Verify missing, inactive, unapproved, or incompatible accounts/mappings block posting through the application path and leave source completion atomic. Verify duplicate source completion does not create another journal or stock effect.
- Use controlled, isolated fixtures to reconcile exact debit/credit amounts: balanced posted journals; no draft/unapproved-entry effects; GL totals matching posted activity; balanced unadjusted and adjusted Trial Balances; approved adjustments reflected once; and Assets = Liabilities + Equity including derived unclosed earnings exactly once.
- Preserve report inclusion of legitimately posted historical accounts after deactivation. Do not hide a real imbalance, exclude posted history to force agreement, or insert balancing figures.
- Prior art: `JournalEntryTest`, `GeneralLedgerTest`, `TrialBalanceTest`, `FinancialStatementsTest`, `AccountingOverviewTest`, `PosSaleAccountingTest`, `PointOfSaleTest`, `ProductionAccountingActivationTest`, `AccountsPayableTest`, `SupplierPurchaseInvoicesTest`, `SupplierPaymentAllocationsTest`, `CashDisbursementsTest`, `ValuedStockMovementTest`, `RecoveredMaterialTest`, `InterveningSourceReconciliationTest`, `OpeningBooksTest`, `SupplierOpeningPayablesTest`, `AccountingPeriodsTest`, `FiscalYearClosingTest`, and `AuthorizedAccountingWorkflowTest`.

### Execution and evidence requirements

- Before changing behavior, use the existing relevant scenarios as a baseline; do not rerun a user-reported failure merely to reconfirm it. Add regression cases for newly uncertain behavior, not an alternate comprehensive test framework.
- Execute focused suites as each dependent change is ready, then run the relevant integrated regression set after implementation is assembled. Observe the actual explicit seeding command on a verified disposable database, an unchanged rerun, and an unsafe-target refusal; tests alone do not replace that smoke evidence.
- Run actual tests through `php artisan test`, recording exact commands, selected database context, passes, failures, skips, and incomplete executions. Do not infer success by reading test names or source.
- Correct implementation-introduced failures; identify pre-existing unrelated failures separately. A skipped, blocked, unexecuted, or environment-limited scenario is not a pass.

## Out of Scope

- Implementing application changes or seeding data as part of the current specification-writing request.
- Installing demo records into production, modifying business connection settings, resetting databases, deleting historical accounts, remapping historical journal lines, or altering working production accounts.
- CPA certification, approval of the proposed chart for actual FCDC books, fabricated approvals, or automatic account/mapping activation.
- Sample opening balances, journal entries, POS activity, inventory movements, financial figures, or supplier transactions in the demo COA seeder. Disposable test fixtures remain permitted.
- A sixth COGS account type, a replacement COA store, a wholesale Laravel/Livewire rewrite, a new sidebar module, or an interface redesign.
- Category-aware Sales/COGS/Inventory routing, expanded source mapping cardinality, or automatic assignment of every proposal to a source.
- Automatically adding supporting accounts outside the 54 proposals, including Card Settlement Receivable/Clearing.
- Depreciation schedules; dividends processing; automatic profit/loss postings to 3040; new VAT settlement/payment logic; automatic rework capitalization or expensing; shrinkage classification automation; or inventory valuation triggered by receipt uploads.
- New AR/customer-invoice subledgers, customer/supplier advance workflows, payroll, fixed-asset disposal modules, or additional accounting functionality merely to make all accounts postable.
- Invented significance thresholds, evidence sufficiency rules, transaction-review approvals, or accounting policies for unresolved cases.
- Relaxing period/cutover/control-account restrictions, rewriting posted journals, manufacturing report coverage, suppressing accounting errors, or hardcoding financial results.
- Permanent browser automation, new testing frameworks, unrelated cleanup, and unrelated business-module changes.

## Further Notes

### Evidence and precedence

- Primary requirements: [Info in every Module.pdf](../../docs/Info%20in%20every%20Module.pdf), especially Section 4 and the adjacent Miscellaneous Expense and approval requirements, pages 9–12. Extracted tables have layout artifacts; use the source PDF and the reconciled base names together rather than interpreting extraction noise as account text.
- Existing findings: [COA investigation](../../docs/chart-of-accounts-investigation.md), [requirements alignment and gap analysis](../../docs/chart-of-accounts-requirements-alignment-gap-analysis.md), and [final requirements reconciliation](../../docs/chart-of-accounts-final-requirements-reconciliation.md). The final reconciliation corrects the earlier speculative “12 Add / 42 Needs Approval” split; that split is not an approved status model.
- Vocabulary: [project glossary](../../GLOSSARY.md). Distinguish approved chart of accounts, statement classification, unclosed earnings, GL-only receivable, AP settlement, direct disbursement, control accounts, valued stock movements, and POS-only VAT payable estimates.
- Architectural constraints: [approved accrual cutover](../../docs/adr/0004-approved-accrual-accounting-cutover.md), [atomic immutable postings](../../docs/adr/0005-atomic-immutable-accounting-postings.md), and [forward-only inventory valuation](../../docs/adr/0006-forward-only-moving-average-valuation.md).
- Earlier reports observed environment `local`, MySQL database `project`, one unapproved sample account, and one unapproved mapping. Those are prior read-only observations, not a fresh database audit, proof of production contents, or permission to reuse that database for seeding. Current configuration inspection still does not establish isolated demo provisioning.
- Testing seams were confirmed by the user during specification preparation. No application implementation, application test execution, account creation, approval, or database mutation is claimed by this spec.

### Outstanding accounting and operational decisions

1. Actual applicability and approved production use of the full proposal against FCDC operations and corporate records.
2. Confirmed statement classifications for loans by maturity, accruals, advances, other operating revenue, taxes, and other policy-sensitive accounts. Provisional demo fields do not settle these questions.
3. Contra-asset presentation for 1510 and Contra-Equity applicability/presentation for 3050. Net-sales treatment for 4090 is preserved; any separate presentation needs approval.
4. Whether 3040 will remain a reporting-only representation or require a future redesigned ledger workflow. Ledger use is blocked under this contract.
5. Whether 2040 is a distinct obligation or net-settlement account, and any approved relation to 2030/1040. No settlement behavior is invented.
6. Approved rework treatment for 5030 and transaction-purpose/approval rules for 5090. No rework posting is authorized here.
7. Miscellaneous Expense evidence sufficiency, significance/unusual-expense treatment, and when transaction-level authorized review is required. Keep 6190 unavailable while unresolved.
8. Any future category split among 4010/4020, 5010/5020, or 1050/1060, and any separately required Card Settlement Receivable/Clearing account.
9. Whether a used account's normal balance can ever change through an approved historical correction. This rollout does not change it.
10. Verifiable persistent demo provisioning and connected-identity evidence. Without this operational prerequisite, prepare and test the implementation but withhold persistent seeding.

These decisions block only the affected activation, posting, or unsafe database execution. They do not block preparing the complete inactive/unapproved demo chart, implementing server-side refusals, or testing independent approved behavior in disposable fixtures. The tracker status indicates readiness to implement this bounded safe contract, not approval of those accounting policies.

### Required implementation delivery report

The implementing agent must return the following ten sections, based on observed work rather than intended work:

1. **Implementation Summary:** actual changes and completed phases.
2. **Files Changed:** every modified/created file, its purpose, and the relevant behavior.
3. **Database Changes:** migrations, seeders, configuration, created records, effective target environment/database, and isolation evidence; explicitly state when seeding was withheld.
4. **Final Chart of Accounts:** all 54 proposals grouped by the seven PDF categories, with Code, Name, Type, Classification, Normal Balance, Active status, Approval status, and Posting eligibility. Identify separately any preserved unrelated or approved supporting accounts; do not include them in the 54 count.
5. **Existing Functionality Preserved:** account maintenance, source posting, financial reporting, permissions, approval, and historical behavior actually checked.
6. **Implemented Accounting Safeguards:** exact enforced behavior for contra accounts, derived earnings, VAT, rework, inventory adjustments, and Miscellaneous Expense, including policy-held refusals.
7. **Integration Verification:** passed integrations, failures, and unverified or blocked integrations separately.
8. **Test Results:** exact commands and actual totals for passed, failed, skipped, and incomplete tests, with environment limitations and pre-existing unrelated failures distinguished.
9. **Outstanding Accounting Decisions:** remaining professor/CPA policies and operational prerequisites, with affected account/workflow and safe current state.
10. **Completion Status:** each of the eight phases classified as **Completed and tested**, **Implemented but not fully verified**, **Blocked by accounting approval**, or **Not implemented**. Explain mixed work within a phase rather than overstating its overall status.

Do not claim the implementation fully complete when required verification, persistent demo provisioning, or a required accounting-policy decision remains unresolved. Update existing implementation documentation after behavior is proven, remove temporary smoke fixtures/scripts, and preserve this specification as the local tracker contract.
