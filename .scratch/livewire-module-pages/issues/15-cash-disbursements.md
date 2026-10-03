# 15: Cash Disbursements

**What to build:** Preserve Cash Disbursements as a standalone demonstration page with existing reference/payee search and method selection, without inventing payment or accounting behavior.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] Cash Disbursements has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current appearance.
- [ ] Reference/payee search and All/Cash/Check/Bank Transfer selection use Livewire; the existing empty dataset remains empty rather than acquiring fabricated disbursements.
- [ ] Cards and table states identify the non-operational payment/accounting state. Zero displays are not represented as verified payment totals.
- [ ] Add Disbursement and other unsupported maintenance actions are visibly unavailable with explanations rather than backend-integration alerts.
- [ ] No payable settlement, payment processing, posting rules, business persistence, or real cash movement is added.
- [ ] Smoke search/method interactions, empty results, unavailable actions, direct entry, responsive presentation, and guest protection in the actual browser.
