# 09: Tax Report

**What to build:** A separately routed internal VAT Tax Report demonstration with period filtering and an isolated printable report.

**Blocked by:** 07: VAT Records.

**Status:** completed

- [x] Tax Report has its own authenticated named route and Tax Compliance-grouped Livewire page/template using the shared shell and current appearance.
- [x] Livewire period selection shows the matching established illustrative VAT transactions and corresponding taxable/VAT/total summary, retaining the existing report headers and metadata behavior.
- [x] Scoped print behavior prints only the selected report, without sidebar, other pages, or POS receipt print rules interfering. Screen and printed report retain a clear placeholder/internal/non-filing disclaimer.
- [x] No official tax return, BIR submission, statutory compliance claim, persisted tax pipeline, or ingestion of POS demo sales is added.
- [x] Smoke multiple periods, expected rows/totals, direct entry, guest protection, and actual print preview in the browser. Preserve readable print and narrow-screen presentation.
