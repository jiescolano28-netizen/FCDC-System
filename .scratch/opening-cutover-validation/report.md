# Opening Cutover, Synthetic Demo, and Accounting Validation

**Status:** Complete for the synthetic demo and automated checks
**Validation date:** October 24, 2026 (Asia/Manila)
**Scope:** Opening journal, opening AP and inventory schedules, production activation, POS sale, inventory/cost posting, Journal Entry, General Ledger, Trial Balance, Financial Statements, Accounting Overview
**Environment:** Isolated SQLite demo database via `.env.demo`; tests run on in-memory SQLite through `phpunit.xml`
**Data:** Synthetic `DEMO` records only. No FCDC balances, no production database, no real supplier or customer records.

> **Safety statement:** The demo database is `storage/app/demo/fcdc-demo.sqlite`. It was rebuilt from scratch for this validation. The local MySQL `project` database was not read or written. No real records were deleted or replaced.

---

## 1. What was validated

| Area | Result |
| --- | --- |
| Opening journal posts and balances | Passed: `814,000.00` debits = `814,000.00` credits |
| Opening AP credit equals approved supplier schedule | Passed: `30,000.00` = `30,000.00` |
| Opening inventory debit equals approved item schedule | Passed: `64,000.00` = `64,000.00` |
| Zero-inventory cutover | Passed without inventing quantities or values |
| Zero-AP cutover | Passed without inventing payables |
| Nonzero AP exact-match gate | Passed; a `49.99` credit against a `50.00` schedule is rejected |
| Production readiness and activation | Passed: `production_ready=true`, `production_activated=true` for the demo |
| Browser E2E: POS sale → Journal Entry → GL → TB → FS → Overview | Passed with no browser console errors |
| Balance sheet equation | Passed: `Assets 817,068.80 = Liabilities 32,478.80 + Equity 784,590.00` |
| 21 official COA accounts, unique codes/titles, all approved | Passed |
| 10 required posting mappings, all approved | Passed |
| Full test suite with GD enabled | **406 passed (4,028 assertions), 0 failed** |

---

## 2. Isolation and safety controls

- Demo connection: `DB_CONNECTION=demo`, SQLite file under `storage/app/demo/`.
- `DemoDatabaseIsolation::verify()` enforces:
  - `APP_ENV=demo` (explicitly allowlisted),
  - `DEMO_ENABLED=true`,
  - the effective write connection is `demo`,
  - session/cache/queue writes use the demo connection,
  - the SQLite file is under `storage/app/demo` and matches the connected database identity.
- The demo seeder refuses to run if any business table already contains data; it never replaces existing records.
- The demo login password is generated at seed time and stored only in the git-ignored `storage/app/demo/credentials.txt`. It is not reproduced in this report.
- A global banner identifies the data as synthetic on every page:
  > SYNTHETIC DEMO DATA — Generated sample records for capstone testing only. These are not FCDC official financial balances, records, or approvals. Do not file, audit, or use them in production.

---

## 3. Synthetic demo dataset

### 3.1 Opening journal (October 24, 2026 midyear cutover)

| Account | Debit | Credit |
| --- | ---: | ---: |
| 1010 Cash on Hand | 250,000.00 | |
| 1020 Cash in Bank | 500,000.00 | |
| 1040 Recovered Materials Inventory | 64,000.00 | |
| 2010 Accounts Payable | | 30,000.00 |
| 3010 Share Capital | | 764,000.00 |
| 4010 Recovered Materials Sales Revenue | | 20,000.00 |
| **Total** | **814,000.00** | **814,000.00** |

The `20,000.00` opening-source income line represents the current-year profit already embedded in the opening assets. It is deliberately **not** posted to `3020 Retained Earnings`; see section 8 for the balance-sheet correction.

### 3.2 Opening schedules

- **Inventory:** six synthetic items totaling `64,000.00` carrying value. `DEMO Scaffold Frames` has quantity `0` and value `0.00`; no quantity or value was invented.
- **Accounts payable:** two synthetic opening invoices, `DEMO-OPEN-1` (`18,000.00`) and `DEMO-OPEN-2` (`12,000.00`), totaling `30,000.00`.
- **Pre-cutover YTD summary (approved):** Sales `100,000.00`, Utilities `80,000.00`, net `20,000.00`, through October 23, 2026.

### 3.3 Post-cutover activity

- Credit purchase `DEMO-PUR-001` for `3,000.00`, then a `1,000.00` bank payment.
- Utility expense `1,500.00`, plus a `250.00` bank-charge journal.
- Reworking-cost offset credit of `800.00`.
- Three seeded POS sales plus one browser-created POS sale.
- Draft purchase, draft expense, draft journal, and an intentionally unbalanced draft journal remain unposted as negative-control records.

---

## 4. Zero-balance coverage

Covered by `tests/Feature/OpeningZeroBalanceCutoverTest.php` (3 tests).

| Scenario | Observed |
| --- | --- |
| Zero inventory items at cutover | Empty approved schedule accepted; no quantity or value invented; readiness shows `Opening stock schedule reconciled`; TB inventory book/schedule both `0.00` |
| Zero-quantity inventory item | Approved line `0.00` quantity / `0.00` value; later stock movement does not invalidate the opening schedule |
| Zero opening AP | No opening invoices; no AP journal line; AP book/schedule both `0.00`; readiness shows `Opening supplier schedule reconciled` |
| Nonzero AP exact match | `50.00` AP credit posts only when the approved schedule is exactly `50.00`; `49.99` is rejected and the journal stays `draft` |

**Design nuance:** a completely zero opening journal (all lines zero) is rejected by design because `OpeningBooksService::assertBalancedLines()` requires positive, equal totals. A business with no assets, liabilities, or equity at all therefore cannot activate through a zero-total journal. Zero inventory and zero AP *are* supported; this limitation is listed in section 10 as a decision point.

---

## 5. AP reconciliation proof

| Layer | Amount |
| --- | ---: |
| Opening journal AP credit | `30,000.00` |
| Approved opening supplier schedule (`18,000 + 12,000`) | `30,000.00` |
| Later purchase `DEMO-PUR-001` | `3,000.00` |
| Later payment `DEMO-PAY-001` | `1,000.00` |
| **AP book balance after posting** | **`32,000.00`** |
| **Supplier/invoice schedule after posting** | **`32,000.00`** |

Browser Trial Balance text:

> Accounts Payable: book 32,000.00; supplier and invoice schedule 32,000.00.

Inventory reconciliation also matched:

> Inventory: book 65,350.00; valued-item schedule 65,350.00.

---

## 6. Browser-level E2E evidence

**Method:** temporary `playwright-core` install driving system Chrome headless against the isolated demo server on `http://127.0.0.1:8001`. No test framework shortcuts were used for the sale or page navigation.

**Artifacts:**
- Screenshots: `.scratch/opening-cutover-validation/screenshots/01`–`10`
- Parsed evidence: `.scratch/opening-cutover-validation/browser-evidence.json`
- E2E script: `C:/Users/JURISH/AppData/Local/Temp/opencode/fcdc-pw/e2e-demo.mjs`

| # | Screen | Evidence |
| --- | --- | --- |
| 01 | Dashboard | Synthetic banner visible |
| 02 | Opening Books & Cutover | `Production activated on October 24, 2026`; supplier and inventory schedules reconciled; `Approved cutover: October 24, 2026` |
| 03 | POS before sale | Demo stock available |
| 04 | POS receipt | `POS-20261023-C8MJACLQ`; 1 × `DEMO Ceramic Tiles` at `90.00`; VAT `10.80`; total `100.80` |
| 05 | Journal Entry | Opening journal and posted `POS-GL-4` visible |
| 06 | General Ledger | `1040 — Recovered Materials Inventory`; opening `64,000.00` debit, debits `3,800.00`, credits `2,450.00`, ending `65,350.00` debit |
| 07 | Trial Balance | Balanced; debits = credits = `821,268.80`; AP and inventory reconciled |
| 08 | Income Statement | Sales `3,990.00`; COGS `2,450.00`; gross profit `1,540.00`; net income `590.00` |
| 09 | Balance Sheet | `Accounting equation balances`; assets `817,068.80` = liabilities `32,478.80` + equity `784,590.00` |
| 10 | Accounting Overview | Available and showing revenue (demo is activated) |

No browser console errors or page errors were captured.

**Notes:**
- The Trial Balance is an aggregated worksheet and does not render journal references. The posted POS journal is proven in Journal Entry and General Ledger (`POS-GL-4`), not by a TB reference column.
- The POS receipt displays `2026-10-23 17:37` because POS numbering/display uses the application UTC timezone, while accounting dates use `Asia/Manila`. The posted journal is dated October 24, 2026 (Manila). This is a pre-existing display timezone inconsistency, not a posting error.

---

## 7. Activation behavior: GL/FS before activation vs TB/Overview

The code intentionally treats the two groups differently:

| Surface | Gate | Behavior before activation |
| --- | --- | --- |
| General Ledger | Posted opening journal / cutover coverage only | Available, with opening balances |
| Financial Statements | Posted opening journal / cutover coverage only | Available (income statement and balance sheet) |
| Trial Balance | `production_activated` must be true | `Trial Balance unavailable` / `Production ready for authorized activation` |
| Accounting Overview | `production_activated` must be true | Unavailable |

This is a real UX asymmetry, not an accidental bypass: GL and FS read the posted opening directly, while TB and Overview are hard-gated on activation. To make the preview state explicit, both GL and FS now show:

> **Pre-activation preview.** Opening balances are posted, but production accounting has not been activated. This ledger/statement is provisional and may be replaced by final cutover activity. Trial Balance and Accounting Overview remain unavailable until an authorized reviewer activates production accounting.

The notice is covered by `OpeningCutoverEndToEndTest` for both statements and disappears after activation. The demo is already activated, so the screenshots show the post-activation state; the pre-activation state is proven by the automated test.

**Decision still needed:** either keep the labelled preview as the intended operator experience, or gate GL/FS until activation. No gating behavior was changed.

---

## 8. Balance-sheet correction found during validation

### Symptom

Before correction, the demo balance sheet did not balance:

- Assets: `817,068.80`
- Liabilities + Equity: `837,068.80`
- Difference: `−20,000.00`

### Cause

`FullDemoSeeder` posted the `20,000.00` current-year pre-cutover profit as a credit to `3020 Retained Earnings`, while `FinancialStatements::unclosedEarnings()` separately adds the approved YTD net income of `20,000.00`. The same profit was therefore counted twice in equity.

### Correction

- The midyear opening now credits `4010 Recovered Materials Sales Revenue` for `20,000.00` instead of `3020 Retained Earnings`.
- `3010 Share Capital` remains `764,000.00` for the midyear case.
- The January 1 case is unchanged (`3010 = 784,000.00`, no YTD summary, no opening income plug).
- `3020` remains in the approved chart; it is simply not used in the synthetic opening.

This matches the established convention in `FinancialStatementsTest` (“balance sheet includes approved pre-cutover year-to-date earnings separately from opening equity”), where opening-source income is excluded from both the income statement and unclosed earnings.

### Result

- Assets: `817,068.80`
- Liabilities: `32,478.80`
- Equity: `784,590.00` (`764,000.00` opening capital + `20,000.00` YTD net + `590.00` post-cutover net)
- `balanced = true`

A regression assertion was added to `FullDemoSetupTest` for both the midyear and January 1 seed scenarios.

---

## 9. Chart of accounts and posting mappings

Verified directly against the seeded demo database:

- **21 active accounts**, **21 unique codes**, **21 unique titles**, **0 unapproved active accounts**.
- Codes: `1010, 1020, 1030, 1040, 1050, 2010, 2020, 3010, 3020, 4010, 4020, 4030, 5010, 6010, 6120, 6130, 6140, 6150, 6160, 6170, 6180`.
- No duplicate account was added and no chart record was altered outside the isolated demo database.

**10 required mappings, all approved:**

| Source | Account |
| --- | --- |
| cash | 1010 Cash on Hand |
| bank | 1020 Cash in Bank |
| card_clearing | 1020 Cash in Bank |
| sales | 4010 Recovered Materials Sales Revenue |
| output_vat | 2020 Output VAT Payable |
| cogs | 5010 Cost of Recovered Materials Sold |
| inventory | 1040 Recovered Materials Inventory |
| accounts_payable | 2010 Accounts Payable |
| recovery_offset | 6010 Reworking Costs |
| adjustment | 6180 Other Operating Expenses |

`card_clearing` shares `1020` by design because the official chart has no separate card-clearing account.

---

## 10. Test results

All commands used `php -d extension=gd vendor/bin/pest` so the GD-dependent inventory attachment tests could run. `php artisan test` was not used because it spawns a PHP subprocess that does not inherit `-d extension=gd`.

| Suite | Result |
| --- | --- |
| Focused accounting/opening/COA/demo tests | **40 passed (526 assertions)** |
| Inventory attachment tests (`InventoryReceiptAttachmentTest`, `InventoryBatchCategoryAttachmentTest`) | **18 passed (89 assertions)** |
| Those two plus `InventoryManagementTest` | **53 passed (408 assertions)** |
| **Full suite** | **406 passed (4,028 assertions), 0 failed, 71.23s** |

The three previously reported GD-related failures were environmental: GD is installed as `C:\xampp\php\ext\php_gd.dll` but `;extension=gd` is still commented in `php.ini`. With the extension loaded for the test process, all three pass. `php.ini` was not modified.

---

## 11. Remaining risks and decisions

1. **True zero-total opening journal is rejected.** Zero inventory and zero AP are supported, but a business with no balances at all cannot activate without positive balanced lines. Decide whether a zero-total cutover should be allowed.
2. **GL/FS pre-activation asymmetry.** The preview is now explicitly labelled. Decide whether to keep the preview or gate these reports until activation.
3. **Current-year earnings convention.** The synthetic opening uses an opening-source income line as the balancing plug. If an operator manually posts current-year profit to `3020 Retained Earnings`, the balance sheet can double count it again. A dedicated current-year earnings account (or a validation rule) would remove the ambiguity, but that would change the 21-account official chart.
4. **Timezone display.** POS receipt/numbering uses UTC while accounting dates use `Asia/Manila`; align the display if audit users must see one date.
5. **No real-data rehearsal.** This validates mechanics with synthetic data only. A dry run against a copy of real balances, real chart, mappings, suppliers, and cutover date is still required before production activation.
6. **Demo credentials.** The demo password is synthetic and stored in `storage/app/demo/credentials.txt`. Rotate or delete it before sharing the demo database.
7. **Sidebar links.** `Financial Statements` and `Notes` links are absent from the sidebar in the current working tree; direct routes still work. This is a pre-existing navigation gap.
8. **CI GD configuration.** The full suite only passes with GD loaded via the CLI flag; CI/local `php.ini` should enable `gd` for `php artisan test` to be green without the flag.

---

## 12. Reproduce

```bash
# Rebuild the isolated synthetic demo (destroys only storage/app/demo/fcdc-demo.sqlite)
rm -f storage/app/demo/fcdc-demo.sqlite
touch storage/app/demo/fcdc-demo.sqlite
php artisan demo:setup --env=demo

# Serve the demo
php artisan serve --env=demo --host=127.0.0.1 --port=8001

# Browser E2E (temporary Playwright install)
cd C:/Users/JURISH/AppData/Local/Temp/opencode/fcdc-pw
node e2e-demo.mjs

# Focused accounting validation
php -d extension=gd vendor/bin/pest \
  tests/Feature/OpeningCutoverEndToEndTest.php \
  tests/Feature/OpeningZeroBalanceCutoverTest.php \
  tests/Feature/OpeningBooksTest.php \
  tests/Feature/FinancialStatementsTest.php \
  tests/Feature/ProposedChartOfAccountsSeederTest.php \
  tests/Feature/SyntheticDemoLabelTest.php \
  tests/Feature/FullDemoSetupTest.php --compact

# GD-dependent inventory attachment coverage
php -d extension=gd vendor/bin/pest \
  tests/Feature/InventoryReceiptAttachmentTest.php \
  tests/Feature/InventoryBatchCategoryAttachmentTest.php \
  tests/Feature/InventoryManagementTest.php --compact

# Full suite
php -d extension=gd vendor/bin/pest --compact
```

---

## 13. Relevant files changed for this validation

| File | Change |
| --- | --- |
| `database/seeders/FullDemoSeeder.php` | Midyear opening uses `4010` instead of `3020` for the `20,000.00` earnings plug; Jan 1 branch unchanged |
| `tests/Feature/FullDemoSetupTest.php` | Regression assertion that the seeded balance sheet balances for midyear and Jan 1 |
| `tests/Feature/OpeningZeroBalanceCutoverTest.php` | Zero inventory, zero AP, zero-quantity item, and exact-AP proofs |
| `tests/Feature/OpeningCutoverEndToEndTest.php` | Pre-activation poster, activation, GL/TB/FS assertions |
| `tests/Feature/ProposedChartOfAccountsSeederTest.php` | 21-account and uniqueness assertions |
| `tests/Feature/SyntheticDemoLabelTest.php` | Demo banner coverage |
| `app/Services/Accounting/OpeningBooksService.php` | Readiness no longer revalidates the approved opening schedule against post-cutover stock movements |
| `app/Livewire/Accounting/GeneralLedger.php`, `FinancialStatements.php` | Pass `productionActivated` to the views |
| `resources/views/livewire/accounting/general-ledger.blade.php`, `financial-statements.blade.php` | Pre-activation preview notices |
| `resources/views/layouts/app.blade.php`, `resources/css/shell.css` | Global synthetic-demo banner |
| `storage/app/demo/credentials.txt` | Rotated synthetic demo password (not reproduced here) |

Unrelated pre-existing working-tree changes were left untouched.
