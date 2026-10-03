# 17: Trial Balance

**What to build:** A separate Trial Balance demonstration page preserving period controls and the report presentation, with honest non-operational status and page-specific printing.

**Blocked by:** 01: Settings and shared application shell.

**Status:** ready-for-agent

- [ ] Trial Balance has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and existing visual structure.
- [ ] From/to period inputs use Livewire and retain the current empty-report behavior. Empty totals/status explicitly explain the absence of an implemented real trial balance; they do not certify balanced company books.
- [ ] Scoped printing prints this page's report, selected periods, and non-operational explanation, without sidebar, receipt, or unrelated tax-document rules hiding the report.
- [ ] Do not calculate balances from demo journals, invent accounting period/closing rules, or add ledger/report persistence.
- [ ] Smoke period changes, direct entry, guest protection, narrow-screen presentation, and actual browser print preview with the explanatory status retained.
