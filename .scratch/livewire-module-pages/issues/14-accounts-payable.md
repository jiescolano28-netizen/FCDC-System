# 14: Accounts Payable

**What to build:** Preserve Accounts Payable as a separate demonstration page with its existing supplier/invoice filters, cards, and empty-state presentation, explicitly unavailable for real payable management.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] Accounts Payable has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current appearance.
- [ ] Supplier/invoice search and All/Unpaid/Partially Paid/Paid selection use Livewire, retaining the current empty dataset rather than fabricating payable records.
- [ ] Amount/open-invoice presentations explain the non-operational state; zero values are not portrayed as verified company obligations or balances.
- [ ] Add Payable and any other unsupported maintenance/payment actions are visibly unavailable with explanations instead of backend-integration alerts.
- [ ] No supplier-invoice lifecycle, payments, accounting posting, business tables, or sample production obligations are added.
- [ ] Smoke search/status interactions, empty results, unavailable actions, direct entry, responsive presentation, and guest protection in the actual browser.
