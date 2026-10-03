# 12: Chart of Accounts

**What to build:** A separate Chart of Accounts demonstration page with the existing sample reference accounts and working search/type/status filters, without claiming account maintenance is implemented.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] Chart of Accounts has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current appearance.
- [ ] Existing sample account codes, names, types, descriptions, and statuses remain clearly illustrative reference data, not an approved production chart or database account records.
- [ ] Livewire code/name search, account-type filtering, status filtering, and combined/empty results display the expected sample accounts.
- [ ] Add and Edit Account controls remain visibly unavailable with explanations instead of clickable backend-integration alerts. Do not invent creation, editing, approval, or posting rules.
- [ ] Establish reuse of this existing sample reference data for Journal Entry and General Ledger, without globally mounting their pages or duplicating a chart of accounts.
- [ ] Smoke known-account searches, type/status combinations, empty results, unavailable actions, direct entry, and guest protection in the actual browser.
