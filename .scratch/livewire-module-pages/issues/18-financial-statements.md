# 18: Financial Statements

**What to build:** A separately routed Financial Statements demonstration preserving Income Statement/Balance Sheet selection, date controls, document headers, and isolated printing without invented financial calculations.

**Blocked by:** 01: Settings and shared application shell.

**Status:** completed

- [x] Financial Statements has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current document appearance.
- [x] Livewire statement-type and date selection update the corresponding document title/date and preserve the existing explicit non-operational body.
- [x] Documents do not portray placeholders or zeros as real financial results and do not derive statements from session demo journals or POS sales.
- [x] Scoped printing isolates the selected document, retaining its title, date, and explanatory limitation rather than printing the receipt or a blank page.
- [x] No income/balance-sheet mappings, accounting policies, ledger calculations, or business persistence are invented.
- [x] Smoke both statement types, date selection, direct entry, guest protection, responsive presentation, and actual browser print preview.

**Verification (2026-10-06):** `php artisan test --compact tests/Feature/FinancialStatementsTest.php` passes (2 tests, 23 assertions), including statement switching/date updates, guest redirection, and demo journal/POS isolation. Authenticated browser smoke confirmed direct entry and narrow-screen layout; browser-generated print preview contained only the selected document and its limitation. The browser's Livewire update request returned HTTP 419 in this local session, so selector/date interaction was verified through the feature test rather than the browser. Full suite: 78 passed; two unrelated public-homepage tests returned HTTP 500.
