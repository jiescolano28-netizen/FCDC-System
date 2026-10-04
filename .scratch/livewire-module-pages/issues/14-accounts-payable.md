# 14: Accounts Payable

**What to build:** Preserve Accounts Payable as a separate demonstration page with its existing supplier/invoice filters, cards, and empty-state presentation, explicitly unavailable for real payable management.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-human

- [x] Accounts Payable has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current appearance.
- [x] Supplier/invoice search and All/Unpaid/Partially Paid/Paid selection use Livewire, retaining the current empty dataset rather than fabricating payable records.
- [x] Amount/open-invoice presentations explain the non-operational state; zero values are not portrayed as verified company obligations or balances.
- [x] Add Payable and any other unsupported maintenance/payment actions are visibly unavailable with explanations instead of backend-integration alerts.
- [x] No supplier-invoice lifecycle, payments, accounting posting, business tables, or sample production obligations are added.
- [ ] Smoke search/status interactions, empty results, unavailable actions, direct entry, responsive presentation, and guest protection in the actual browser.

## Comments

**Verification (2026-10-04):** Focused Accounts Payable feature tests pass (2 tests, 33 assertions), including the authenticated route, guest redirect, bound search/status fields, all status options, the empty dataset, and unavailable actions. The production Vite build succeeds. Full suite: 71 passed, 1 unrelated `VatReturnPreparationTest` failure (guest VAT-return route returned 500 instead of redirecting). Browser direct entry redirected to Login for a guest as expected; browser login then returned Laravel's expired-page response (419), so authenticated visual and interaction smoke remains blocked pending a working local browser session.
