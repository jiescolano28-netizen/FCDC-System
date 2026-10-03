# 19: Complete cutover and cross-module verification

**What to build:** Complete the transition to all 18 separately routed screens across the six modules, remove the obsolete monolith implementation, and prove the integrated real/demo boundaries and navigation work before merging the migration branch.

**Blocked by:** 03: Inventory Overview; 05: Dashboard; 06: Dashboard-owned Reports; 08: VAT Summary; 09: Tax Report; 10: VAT Return Preparation demonstration; 11: Accounting Overview; 13: Journal Entry; 14: Accounts Payable; 15: Cash Disbursements; 16: General Ledger; 17: Trial Balance; 18: Financial Statements. These leaf dependencies transitively include every other implementation ticket.

**Status:** ready-for-agent

- [ ] All 18 screens have authenticated named routes and module-grouped Livewire pages/templates: Dashboard and Reports; Inventory overview and management; POS; four Tax Compliance screens; eight Accounting screens; Settings. Every screen uses the shared shell.
- [ ] Shared navigation and all accounting quick actions reach their intended page. Direct URLs, refresh, browser Back/Forward, active highlighting, parent dropdown expansion, sidebar collapse, logout, and narrow-screen navigation work without hidden-section switching or dead links.
- [ ] Remove superseded monolith sections/file, global switching/rendering scripts, obsolete modals, unused assets/layouts, and superseded component paths after consumers migrate. Leave no permanent compatibility aliases, duplicate implementations, or references to retired paths.
- [ ] Inventory overview/management and inventory metrics show persisted stock. Prove with isolated data that demo POS checkout only reduces session POS stock, while Dashboard/Reports display demo sales as demonstrations and VAT fixtures remain separate.
- [ ] Cart, demo sales, simulated POS stock, journal draft/history, and company settings survive cross-module navigation and refresh in the signed-in session. Logout and subsequent login do not leak demo state between employees; two tabs follow the approved shared-session behavior.
- [ ] Exercise real Inventory CRUD, pricing, images, searches/filters and unchanged management/JSON response contracts. Existing homepage, login, logout, and guest access contracts remain intact; update affected existing behavior tests rather than source/wiring assertions.
- [ ] Exercise VAT filters/details/periods, journal invalid/valid/clear/history behavior, and all Accounting selectors/non-operational states. Unsupported actions are explained as unavailable; no fake business records, official returns, or invented accounting rules are introduced.
- [ ] Browser-smoke chart updates across navigation, image viewing, and receipt/Tax Report/VAT Return/Trial Balance/Financial Statement print previews. Documents isolate the correct content and preserve applicable demo/non-filing/non-operational explanations.
- [ ] Preserve the current green-and-gold appearance; do not introduce an unrelated redesign. Module-specific scripts do not execute against absent page content, and no page mounts all other screens.
- [ ] Finish with affected behavior checks and an actual authenticated cross-module browser smoke before merging. Record exercised evidence and update migration documentation; remove throwaway verification scaffolds. Do not close/rewrite the earlier functional-application tickets or relax their production accounting/tax release restrictions.
