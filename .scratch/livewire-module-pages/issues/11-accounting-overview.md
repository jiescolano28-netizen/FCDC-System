# 11: Accounting Overview

**What to build:** A separate Accounting Overview page preserving its visual structure while clearly explaining that real accounting balances and reports are not implemented by this migration.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] Accounting Overview has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and existing cards/table appearance.
- [ ] Balance and recent-entry presentations explicitly explain the non-operational accounting state. Existing zero/empty displays must not be presented as verified real revenue, expenses, net income, payables, or posted activity.
- [ ] Quick actions navigate to the separate Journal Entry, General Ledger, Trial Balance, and Financial Statements routes as their tickets land. Do not retain global hidden-section switching or offer broken destinations.
- [ ] Session demo journals and POS sales do not automatically become real accounting postings or calculated balances. Do not invent account mappings or report definitions.
- [ ] Smoke direct entry, guest protection, the explicit non-operational states, and current layout in the actual browser. Final cross-page quick-action coverage occurs once its destinations have landed.
