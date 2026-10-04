# 12: Chart of Accounts

**What to build:** A separate Chart of Accounts demonstration page with the existing sample reference accounts and working search/type/status filters, without claiming account maintenance is implemented.

**Blocked by:** 01: Settings and shared application shell.

**Status:** completed

- [x] Chart of Accounts has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current appearance.
- [x] Existing sample account codes, names, types, descriptions, and statuses remain clearly illustrative reference data, not an approved production chart or database account records.
- [x] Livewire code/name search, account-type filtering, status filtering, and combined/empty results display the expected sample accounts.
- [x] Add and Edit Account controls remain visibly unavailable with explanations instead of clickable backend-integration alerts. Do not invent creation, editing, approval, or posting rules.
- [x] Establish reuse of this existing sample reference data for Journal Entry and General Ledger, without globally mounting their pages or duplicating a chart of accounts.
- [x] Smoke known-account searches, type/status combinations, empty results, unavailable actions, direct entry, and guest protection in the actual browser.

## Comments

**Verification (2026-10-04):** The focused feature tests pass (3 tests, 23 assertions). Browser smoke covered authenticated direct entry, code search (`1100` → Accounts Receivable), combined name/type/status filters (`accounts` + Liability + Active → Accounts Payable), inactive and unmatched empty results, reload, and guest redirection to Login. The Add Account button was disabled in the browser; the matched sample row displayed Edit, and the feature test verifies both controls carry the native disabled attribute. No browser console errors were observed.
