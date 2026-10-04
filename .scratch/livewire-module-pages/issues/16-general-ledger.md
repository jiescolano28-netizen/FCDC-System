# 16: General Ledger

**What to build:** A separately routed General Ledger demonstration retaining sample-account and date selectors, while clearly showing that real ledger posting/reporting is not available.

**Blocked by:** 12: Chart of Accounts.

**Status:** ready-for-agent

- [x] General Ledger has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and existing table/toolbar appearance.
- [x] All-accounts/sample-account selection and from/to date inputs use Livewire and the established sample reference accounts. They remain usable with the existing empty ledger dataset.
- [x] The empty state explains that no real ledger calculation is implemented; do not create rows or running balances from demo journals or POS sales.
- [x] Do not add accounting posting rules, ledger persistence, approved account mappings, or fabricated ledger activity.
- [ ] Smoke account/date selections, empty/non-operational states, direct entry, responsive presentation, and guest protection in the actual browser.
## Comments

**Verification (2026-10-04):** `GeneralLedgerTest.php` passed (2 tests, 23 assertions). The full Pest suite ran with isolated in-memory SQLite: 73 passed and 3 failed in `ExampleTest`, `HomepageTest`, and `VatReturnPreparationTest`. Browser direct entry as a guest redirected to Login. In an isolated browser smoke setup, login reached Dashboard but a subsequent General Ledger navigation redirected to Login; account/date interaction, empty-state presentation, and responsive visual checks remain unverified in the browser. Ticket remains open for the required browser smoke.
