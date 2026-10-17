# Accounting module completion — specification

**Status:** resolved (implemented and verified)

## Context

`docs/Info in every Module.pdf` describes the Accounting Module as: Chart of Accounts,
Journal Entry, General Ledger, Trial Balance (Unadjusted and Adjusted), Financial
Statements, and Notes to Financial Statements / Disclosure. The earlier
`chart-of-accounts-capstone-demo` effort delivered the Chart of Accounts and manual
journal controls. This effort completes the remaining accounting-report contract:

- Trial Balance must present an unadjusted trial balance, adjusting entries, and an
  adjusted trial balance that is the basis for the financial statements, with beginning
  balances, period movement, ending balances, totals, and an explicit balanced /
  out-of-balance statement.
- Notes to Financial Statements / Disclosure must auto-generate figures from the
  General Ledger / Adjusted Trial Balance, let a human review and write explanatory
  notes, distinguish generated from manual content, record the reporting period and
  source, track review / approval status with audit, protect finalized disclosures, and
  print/export with the financial statements without claiming audit or CPA certification.

## Scope

### 1. Adjusting entries

- Add an `entry_type` (`standard` | `adjusting`) to `accounting_journals`, defaulting to
  `standard` for existing rows.
- Manual adjusting entries reuse the existing manual-journal lifecycle
  (`draft → pending_review → approved → posted`) and therefore remain authorized,
  referenced, explained, and separately approvable.
- Account 3040 and the policy-held accounts keep their existing posting restrictions.
- Corrections of an adjusting entry keep the adjusting classification on the linked
  reversal and replacement.

### 2. Unadjusted / adjusted Trial Balance

- Unadjusted balances exclude the selected period's adjusting entries but still include
  any earlier posted adjustments already in the ledger.
- Adjustments show the selected period's adjusting movement.
- Adjusted balances equal unadjusted plus adjustments and reconcile exactly with all
  posted activity through the as-of date (the financial-statement basis).
- Opening balances and standard period movement are retained; totals report any
  out-of-balance worksheet without a balancing plug.

### 3. Notes to Financial Statements / Disclosure

- New `accounting_disclosure_notes` table storing reporting period, note reference,
  title, body, `disclosure_type` (`generated` | `manual`), `source_metric`, captured
  `generated_amount_cents`, `status` (`draft` | `pending_review` | `approved` |
  `finalized`), preparer, approver, and finalizer audit columns.
- Generated figures are derived from the adjusted trial balance for the reporting
  period: Cash and cash equivalents, Accounts receivable, Inventory, Accounts payable,
  and net Revenue. Creating a generated note captures the figure, its source metric, and
  the reporting period.
- Controls: only `accounting.manage-disclosures` may prepare/submit; only
  `accounting.approve-disclosures` may approve/finalize; approval requires a different
  employee from the preparer; content is immutable after submission; finalized notes
  cannot be edited or deleted.
- The page is printable and explicitly states the disclosures are management-prepared
  and are not audited, examined, or certified by a CPA.

## Out of scope

- No expansion of General Ledger or Financial Statements beyond verification and
  regression fixes.
- No statutory/CPA certification claims.

## Verification

- `php artisan test` (Pest, in-memory SQLite): full suite green.
- Focused coverage in `TrialBalanceTest`, `JournalEntryTest`,
  `NotesToFinancialStatementsTest`, `FinancialStatementsTest`, `ModulePermissionTest`.

## Open policy items (not blocking implementation)

- CPA / instructor confirmation of contra-account presentation (1510, 3050) and the
  disclosure note set required for filing. These require human approval and are tracked
  as outstanding items in `implementation-report.md`.
