# 04: POS

**What to build:** A standalone POS page using Livewire for the current cart and demonstration checkout, preserving receipts and product viewing while keeping simulated activity separate from real stock and business transactions.

**Blocked by:** 01: Settings and shared application shell.

**Status:** in-progress: browser smoke blocked by Livewire HTTP 419

- [x] POS has its own authenticated named route, module-grouped Livewire page/template, and shared shell with the current product/cart appearance.
- [x] Product search, adding/removing materials, quantity changes, cart totals, empty-cart behavior, and existing available-stock boundaries work through Livewire. Retain the existing demonstration calculation; do not add a paid-sale workflow, payment processing, or new sales/pricing rules.
- [x] Cart, completed demo sales, and simulated POS stock reductions survive navigation and refresh within the signed-in session. Tabs share this session state, and logout clears it without leaking to a subsequent employee.
- [x] With 10 real units and a demo checkout of 2, POS may show 8 simulated units, but persisted stock and real Inventory pages remain at 10. Checkout never writes sales or inventory business records.
- [x] Completed demo-sale details remain available to Dashboard/Reports. Checkout feedback and printed receipts explicitly identify demonstration activity, not a recorded sale.
- [x] Receipt contents retain the current line/totals behavior and use company details from the session Settings values across routes. Receipt printing targets only the receipt; product image viewing remains usable through scoped JavaScript.
- [ ] Smoke search, cart edits, leaving/returning, refresh, demo checkout, receipt print preview, and logout/relogin in the actual browser. Prove that the demo checkout leaves persisted stock unchanged using isolated records.

> Browser attempt: the isolated app rendered `/pos`, but the Livewire update POST returned HTTP 419, so interactive checkout and print preview remain unverified in-browser.
