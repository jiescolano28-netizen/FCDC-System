# Chart of Accounts Investigation Report

**Scope:** Source inspection, test-source review, and a read-only query of the currently configured database. No application code, configuration, or database records were changed. No tests were executed. “Implemented” below means the relevant code path exists; test evidence is identified separately and should not be read as a test run.

## 1. Module Overview

The COA is a **persisted account-maintenance and approval module**, not a seeded sample chart. Its authenticated Livewire page reads and manages `accounting_accounts`, and also maintains source-to-account mappings in `accounting_posting_mappings`.

The page provides account search, type/status filters, an account form, and account/mapping approval actions. Accounts and mappings are persisted, but posting eligibility is narrower than mere existence: an account must be active and approved; mappings must also be approved and meet source-classification rules where defined.

There is no parent-account field or hierarchy in the account schema. Account codes are stored as strings and are unique, but the implementation does not impose a numbering pattern.

**Current database observation:** A read-only `php artisan tinker` query returned one account, `Sample — Journal Account` (`Asset`, `cash`, debit, active, not approved, unused), and one `cash` mapping to that account, also not approved. This describes only the database connection configured in this workstation; it does **not** establish the production chart or production database contents.

## 2. Relevant Files

| Path | Responsibility |
|---|---|
| `routes/web.php:67–74` | Registers the authenticated `accounting.chart-of-accounts` Livewire route at `/accounting/chart-of-accounts`, protected by `auth` and `accounting.view`. |
| `app/Livewire/Accounting/ChartOfAccounts.php:10–281` | COA page behavior: account types/classifications, mapping sources, form state, validation, CRUD, approvals, filtering, and permission checks. |
| `resources/views/livewire/accounting/chart-of-accounts.blade.php:1–205` | Account form, search/filter controls, account table, approval actions, and source-mapping table. |
| `app/Models/AccountingAccount.php:10–65` | Persisted account model; approval reset rules, used-account identity/deletion guards, `approver()` and `mappings()` relationships, posting eligibility. |
| `app/Models/AccountingPostingMapping.php:8–56` | Mapping model; mapping approval reset, account/approver relationships, source-classification and posting-eligibility check. |
| `database/migrations/2026_10_10_020302_create_accounting_accounts_table.php:14–27` | Creates the account table, columns, unique code, and approver foreign key. |
| `database/migrations/2026_10_10_020302_create_accounting_posting_mappings_table.php:14–22` | Creates source mappings, unique source, nullable account foreign key, and approver foreign key. |
| `app/Support/RolePermissionCatalog.php:37–43` | Defines accounting view, maintenance, and approval permissions. |
| `database/seeders/RolePermissionSeeder.php:11–30` | Creates permissions and assigns all catalog permissions to the System Administrator role; does not seed accounts. |
| `tests/Feature/ChartOfAccountsTest.php:11–312` | Test source for account access, create/approval, classifications, filtering, deactivation, mapping approval, immutability, and authorization. Not executed during this investigation. |
| `tests/Feature/ModulePermissionTest.php:40–81` | Test source for `accounting.view` route protection, including COA. Not executed. |

No dedicated COA controller, Form Request, policy, repository, or COA-specific service was found in the inspected implementation. The Livewire component and model events contain the maintenance and approval logic. The accounting page route itself uses `accounting.view`; mutation actions perform additional server-side permission checks.

### Related accounting files

The following files use COA accounts or mappings outside the COA page:

- `app/Models/AccountingJournalLine.php:32–40` — journal line belongs to an account.
- `app/Models/AccountingYtdSummaryLine.php:32–40` — YTD summary line belongs to an account.
- `app/Livewire/Accounting/JournalEntry.php:160` and `app/Services/Accounting/ManualJournalService.php:242–246` — approved-account selection and journal eligibility.
- `app/Livewire/Accounting/GeneralLedger.php:53–99`, `app/Livewire/Accounting/TrialBalance.php:69–77`, and `app/Livewire/Accounting/FinancialStatements.php:122–234` — account-backed ledger and report queries.
- `app/Services/Accounting/OpeningBooksService.php` — approved accounts used in opening balances and YTD summaries.
- `app/Services/Accounting/PosSalePostingService.php:147–214` and `app/Livewire/Pos/PointOfSale.php:160–161` — POS posting through approved mappings.
- `app/Services/Accounting/SupplierPurchaseService.php`, `CashDisbursementService.php`, `SupplierPurchaseCorrectionService.php`, and `SupplierPurchaseVatReclassificationService.php` — account and mapping checks for supplier purchases, payments, corrections, and VAT reclassification.
- Inventory services including `app/Services/Inventory/RecordValuedStockMovement.php` and recovery/correction services — mapped accounts for inventory, movement counterparts, and recovered-material postings.
- Related tests include `tests/Feature/JournalEntryTest.php`, `GeneralLedgerTest.php`, `TrialBalanceTest.php`, `FinancialStatementsTest.php`, `PosSaleAccountingTest.php`, `AccountsPayableTest.php`, `CashDisbursementsTest.php`, `SupplierPurchaseInvoicesTest.php`, and `ValuedStockMovementTest.php`. These were inspected only as source; they were not run.

## 3. Database Structure

### `accounting_accounts`

Defined in `database/migrations/2026_10_10_020302_create_accounting_accounts_table.php`.

| Column | Migration type | Notes |
|---|---|---|
| `id` | `bigint` auto-increment | Primary key. |
| `code` | `string(30)` | Unique constraint; no enforced numeric or hierarchical format. |
| `name` | `string(150)` | Required by schema and UI validation. |
| `description` | `text`, nullable | Optional description. |
| `type` | `string(20)` | No database enum/check constraint. |
| `classification` | `string(50)` | No database enum/check constraint. |
| `normal_balance` | `string(10)` | No database enum/check constraint. |
| `is_active` | `boolean`, default `true` | Cast to boolean by model. |
| `approved_at` | `timestamp`, nullable | Account approval timestamp. |
| `approved_by` | nullable foreign ID to `employees` | `nullOnDelete()`. |
| `used_at` | `timestamp`, nullable | Marks that the account has been used. |
| `created_at`, `updated_at` | timestamps | Standard Laravel timestamps. |

There is **no** account parent ID, hierarchy/depth, account group table, or explicit account-number segment structure.

### `accounting_posting_mappings`

Defined in `database/migrations/2026_10_10_020302_create_accounting_posting_mappings_table.php`.

- `id`: auto-increment primary key.
- `source`: `string(40)`, unique.
- `accounting_account_id`: nullable foreign ID to `accounting_accounts`, `nullOnDelete()`.
- `approved_at`: nullable timestamp.
- `approved_by`: nullable foreign ID to `employees`, `nullOnDelete()`.
- Timestamps.

### Other tables referencing accounts

Migration references show COA foreign keys in:

- `accounting_journal_lines.accounting_account_id` — required FK to `accounting_accounts`, with an index alongside journal ID.
- `accounting_ytd_summary_lines.accounting_account_id` — required FK; unique within a YTD summary.
- `supplier_purchase_lines.accounting_account_id` — nullable FK with delete restriction.
- `cash_disbursements.money_account_id` and `cash_disbursement_lines.accounting_account_id` — required FKs.
- `supplier_purchase_correction_lines.accounting_account_id` and `consumed_accounting_account_id` — account FKs with delete restriction.
- Supplier purchase correction payments’ `money_account_id` — account FK with delete restriction.
- `recovered_material_assessments.counterpart_accounting_account_id` — nullable account FK added by migration.
- Supplier purchase VAT reclassification lines’ account and consumed-account IDs — FKs with delete restriction.

These are explicit accounting relationships. VAT Records themselves reference POS transactions, not COA account IDs.

### Account population and current records

No account or mapping seed code was found under `database/seeders`, `database/factories`, or `app/Console`. The codebase and `docs/accounting-workflow-design.md` state that illustrative chart records are not seeded as production accounts.

The workstation’s current configured database returned the single account and mapping described in **Module Overview**. That record is not approved, so it is not eligible for posting. Its name/code suggest sample data, but its origin cannot be established from the query alone.

## 4. Available Features

| Capability | Observed implementation |
|---|---|
| Listing/viewing | Livewire page lists persisted accounts and displays code, name, description, type, classification, normal balance, active state, and approval state. |
| Creation | Form persists account code, name, optional description, type, classification, normal balance, and active status. New accounts are left unapproved. |
| Editing | Maintainers can load an account into the form and save changes. Meaningful changes clear approval. |
| Deletion | Unused accounts can be deleted. Used accounts are blocked in the action and by the model event. |
| Deactivation/reactivation | Action toggles `is_active`. Deactivation retains approval timestamp but inactive accounts fail `isApprovedForPosting()`; reactivation clears approval. |
| Account approval | Separate permission/action; inactive accounts cannot be approved. |
| Search | Case-insensitive substring match over code, name, and description. |
| Filtering | Account type and active/inactive status filters; filters can be combined with search. |
| Sorting | Results are ordered by account code. No user-selected sorting is present. |
| Mapping maintenance | Page supports choosing an account for known posting sources and saving the mapping. Mapping changes clear mapping approval. |
| Mapping approval | Separate approval action requires that the mapped account is active and approved. |
| Pagination | No pagination is present in the COA account query; it retrieves the matching collection. |
| “View account details” | No separate detail page or detail action was found; account data is shown in the list/form. |

**Test evidence, not execution evidence:** `tests/Feature/ChartOfAccountsTest.php` contains tests for create/approval, type/classification matching, search/filtering, deactivation/reactivation, mapping approval reset, used-account restrictions, and server authorization. No result from those tests is claimed here.

## 5. Business Rules

### Validation and approval

`ChartOfAccounts::saveAccount()` validates:

- Account code: required string, maximum 30 characters, unique in `accounting_accounts`, ignoring the current account during edit.
- Name: required string, maximum 150 characters.
- Description: optional string, maximum 2,000 characters.
- Type: one of `Asset`, `Liability`, `Equity`, `Revenue`, `Expense`.
- Classification: one of the component’s enumerated classifications, and must match the selected type.
- Normal balance: `debit` or `credit`.
- Active state: boolean.

Creation and edits that alter values clear `approved_at` and `approved_by`. The model also clears approval when code, name, description, type, classification, normal balance, or activation status is changed in the relevant conditions. Account approval is a separate action with a separate permission.

### Used accounts and deletion

- `markUsed()` sets `used_at` when posting paths call it.
- Once used, code, type, and classification cannot be changed; this is enforced in both the Livewire save path and the model saving hook.
- Used accounts cannot be deleted; both the action and model deletion hook reject it.
- Name, description, and normal balance are not included in the model’s used-account identity immutability guard. Changes to name/description/normal balance clear approval through the model event.
- Deactivating a used account is allowed. It preserves the account record and used timestamp, while the active-account check prevents new posting.

### Rules not found

- No enforced account-code format, prefix, range, or parent/child validity rules.
- No parent/child relationship exists in the account table.
- No rule binds normal balance to account type or classification; the form accepts either debit or credit for every valid type/classification combination.
- No database-level enum/check constraints for type, classification, or normal balance.
- No general account-use lookup in the delete guard; it relies on `used_at`. Foreign keys in other tables additionally restrict deletion in some relationships.
- Inactive accounts cannot pass `isApprovedForPosting()`. Some account-selection queries filter active accounts directly; historical report queries may still include accounts with prior posted activity.

### Mapping rules

- Mapping sources are explicitly enumerated in the Livewire component.
- Save validation requires an existing **active** account.
- For sources in `AccountingPostingMapping::REQUIRED_CLASSIFICATIONS`, the mapped account must have the source-compatible classification.
- `disbursement_method:Other` is limited to `cash` or `bank` classification in the save action.
- Changed mappings lose approval; approval requires that the linked account is active and approved.
- The mapping model’s `isApprovedForPosting()` checks its approval, the account’s active/approved state, and classification for sources in `REQUIRED_CLASSIFICATIONS`.
- Some mapping sources such as `recovery_offset` and `adjustment` have no classification requirement in that constant. Other service-specific checks may apply.

## 6. Account Classifications

### Types and classifications

Types are defined in `ChartOfAccounts.php:12–36`. Classifications are hardcoded there, paired with an allowed type:

| Type | Classification keys and labels |
|---|---|
| Asset | `cash` — Cash; `bank` — Bank; `accounts_receivable` — Accounts receivable; `card_clearing` — Card settlement receivable / clearing; `inventory` — Inventory; `equipment` — Equipment; `other_current_asset` — Other current asset; `other_noncurrent_asset` — Other non-current asset; `input_vat` — Input VAT |
| Liability | `accounts_payable` — Accounts payable; `output_vat` — Output VAT; `other_current_liability` — Other current liability; `other_noncurrent_liability` — Other non-current liability |
| Equity | `capital` — Capital; `retained_earnings` — Retained / accumulated earnings; `other_equity` — Other equity |
| Revenue | `sales` — Sales; `other_income` — Other income |
| Expense | `cost_of_goods_sold` — Cost of goods sold (Expense); `operating_expense` — Operating expense; `other_expense` — Other expense |

COGS is an **Expense** classification, not a separate account type.

### Codes, names, and normal balances

No approved production chart or code/naming convention can be derived from the source declarations. The current configured database query returned only:

| Code | Name | Type / classification | Normal balance | Active / approved |
|---|---|---|---|---|
| `Sample` | `Journal Account` | Asset / `cash` | Debit | Active / not approved |

The code is stored as a string and uniqueness is enforced; no numeric-only convention is imposed. The classification/type pairing is enforced in the Livewire save validation, not by a database constraint. Debit/credit is validated as a two-value choice, but no type/classification-to-normal-balance relationship is enforced.

## 7. Module Integrations

The integration descriptions below are based on source paths and test files; integration tests were **not executed**.

| Module | COA relationship and observed state |
|---|---|
| Journal Entry | `JournalEntry` lists active, approved accounts. `ManualJournalService` checks that journal accounts remain active and approved, with additional restrictions for controlled classifications. Journal lines persist account IDs through `accounting_journal_lines`. Relevant test source: `tests/Feature/JournalEntryTest.php`. |
| General Ledger | Ledger reads account-linked posted journal lines and requires approved accounts for selection; historical activity can include inactive accounts. Test source: `tests/Feature/GeneralLedgerTest.php`. |
| Trial Balance | Aggregates journal lines by account and displays account code/balance information. Test source: `tests/Feature/TrialBalanceTest.php`. |
| Financial Statements | Uses account type, classification, normal balance, approval, and journal/YTD lines to form statement totals and buckets. Test source: `tests/Feature/FinancialStatementsTest.php`. |
| Accounts Payable / supplier purchases | Supplier purchase and settlement paths use approved Accounts Payable mappings and persisted account IDs. Supplier purchase lines reference accounts directly. Test sources include `AccountsPayableTest.php` and `SupplierPurchaseInvoicesTest.php`. |
| Cash Disbursements | Direct money accounts and allocation accounts reference COA IDs. Supplier settlement, inventory purchase allocation, and additional payment methods have account/mapping checks. Test source: `CashDisbursementsTest.php`. |
| Inventory | Valued stock movements use approved Inventory and counterpart mappings; related correction/recovery services use account IDs and mark accounts used. Test source: `ValuedStockMovementTest.php`. |
| POS | `PointOfSale` calls `PosSalePostingService`, which requires approved mappings for cash, sales, VAT, COGS, and inventory; it creates posted journal lines against those account IDs and marks accounts used. Test source: `PosSaleAccountingTest.php`. The workstation’s current DB has only a pending cash mapping, so this database state does not demonstrate a fully configured POS posting chart. |
| VAT Compliance | VAT Records and reports use `PosVatRecord` and POS transactions, not COA account IDs. POS accounting posting does use the configured `output_vat` mapping for its ledger journal. VAT workflow pages are therefore not directly mapped to COA records, even though POS ledger posting is. |

Mapping source names and required classifications are hardcoded in `ChartOfAccounts.php` and `AccountingPostingMapping.php`. Reporting classifications and certain account-selection exclusions are also hardcoded in report and operational modules.

## 8. Permissions and Security

- The COA route requires authentication and `accounting.view`.
- Account creation/editing, activation changes, deletion, and mapping maintenance call server-side checks for `accounting.maintain-accounts`.
- Account and mapping approval call server-side checks for `accounting.approve-accounts`.
- The view uses `@can` to hide controls, but authorization is not solely presentational: Livewire actions also perform permission checks.
- The permission names and labels are defined in `app/Support/RolePermissionCatalog.php`; `RolePermissionSeeder` creates them and grants the catalog to the System Administrator role when that role exists.
- Test source covers unauthenticated/view-only access and rejected unauthorized actions in `ChartOfAccountsTest.php` and `ModulePermissionTest.php`. Those tests were not run.
- Approval actor IDs reference `employees`; the migration nulls the approver reference if the employee is deleted.

## 9. Issues and Limitations

Confirmed from the inspected code:

1. **No hierarchy:** No parent-account column or account-group relationship exists.
2. **No fixed code convention:** Only length and uniqueness are enforced; no numbering scheme or account-code parsing exists.
3. **No normal-balance/type rule:** Debit/credit is a valid choice for any type/classification; no rule verifies the expected balance.
4. **Hardcoded classifications and mappings:** Classifications, mapping source labels, expected source classifications, and several report/account selection rules are defined in code rather than data.
5. **Approval is required for posting:** Newly created/changed accounts require separate approval. New/changed mappings also require approval.
6. **Current configured database is not a usable production chart:** The read-only query found one unapproved sample-named account and one unapproved cash mapping; no approved account/mapping was observed in that configured DB.
7. **“Used” status depends on application paths:** `used_at` is set by posting paths that call `markUsed()`. The deletion model guard checks that marker; the schema itself does not derive it from journal-line references. Some other tables have restrictive foreign keys.
8. **Potential stale test/view expectation:** `tests/Feature/ChartOfAccountsTest.php:22–33` expects “No accounts match the selected filters.” The inspected view’s empty state says “No accounts found” at `resources/views/livewire/accounting/chart-of-accounts.blade.php:145–148`. This source mismatch was not verified by running the test.
9. **Runtime coverage unverified here:** Test files exist for COA and accounting consumers, but no suite or UI smoke test was run during this investigation.
10. **No COA-specific API/controller surface found:** Maintenance is via the authenticated Livewire page/actions; no dedicated REST API or separate COA controller/Form Request was found.
11. **Production data is unverified:** The account query inspected only the current local configured connection; no claim is made about production records.

No requirements comparison or recommendations are included.

## 10. Summary Table

| Feature | Implementation status | Evidence | Notes |
|---|---|---|---|
| Authenticated COA page | Implemented in source | `routes/web.php:73`; `ChartOfAccounts.php`; `chart-of-accounts.blade.php` | Requires `accounting.view`. |
| Persisted account create/edit | Implemented in source | `ChartOfAccounts.php:119–182`; accounts migration | New/changed accounts lose approval. |
| Account search and type/status filters | Implemented in source | `ChartOfAccounts.php:244–265` | Search spans code/name/description; results ordered by code. |
| Account delete/deactivate/reactivate | Implemented in source | `ChartOfAccounts.php:192–210`; `AccountingAccount.php:29–33` | Used accounts cannot be deleted; deactivation is allowed. |
| Account approval | Implemented in source | `ChartOfAccounts.php:184–190`; `AccountingAccount.php:61–64` | Requires separate approval permission; inactive accounts cannot be approved. |
| Parent/child account hierarchy | Not present | Account migration and model | No parent/group fields or relationships found. |
| Account code format | Not present beyond basic validation | `ChartOfAccounts.php:122–129`; account migration | Unique string, max 30; no numbering pattern. |
| Classification/type matching | Implemented in Livewire validation | `ChartOfAccounts.php:12–36, 132–136` | No corresponding database check constraint. |
| Normal balance validation | Partially implemented | `ChartOfAccounts.php:128` | Only `debit`/`credit`; no pairing rule by type/classification. |
| Source-account mappings | Implemented in source | `ChartOfAccounts.php:38–50, 212–242`; mapping model/migration | Fixed source list; changes require renewed approval. |
| Journal/report references to accounts | Implemented in source | Journal line and YTD line models; accounting Livewire/services | Test sources exist; not run here. |
| POS and operational-account mappings | Implemented in source, configured state unverified | POS/accounting and inventory/supplier services | Current configured DB lacks approved mappings needed to post. |
| VAT page integration with COA | No direct COA reference found | VAT page/model sources | POS ledger posting separately uses an Output VAT mapping. |
| Seeded production account records | No source seeder found | Seeder search; accounting documentation | Current configured database’s single sample-named row is not evidence of a production chart. |
