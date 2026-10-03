# 16: General Ledger

**What to build:** A separately routed General Ledger demonstration retaining sample-account and date selectors, while clearly showing that real ledger posting/reporting is not available.

**Blocked by:** 12: Chart of Accounts.

**Status:** ready-for-agent

- [ ] General Ledger has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and existing table/toolbar appearance.
- [ ] All-accounts/sample-account selection and from/to date inputs use Livewire and the established sample reference accounts. They remain usable with the existing empty ledger dataset.
- [ ] The empty state explains that no real ledger calculation is implemented; do not create rows or running balances from demo journals or POS sales.
- [ ] Do not add accounting posting rules, ledger persistence, approved account mappings, or fabricated ledger activity.
- [ ] Smoke account/date selections, empty/non-operational states, direct entry, responsive presentation, and guest protection in the actual browser.
