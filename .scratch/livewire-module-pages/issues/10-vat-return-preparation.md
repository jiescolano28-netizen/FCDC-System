# 10: VAT Return Preparation demonstration

**What to build:** Preserve the existing VAT Return Preparation interface as its own clearly labeled demonstration page, including period selection, company-name editing, illustrative totals, and printing.

**Blocked by:** 07: VAT Records.

**Status:** completed

- [x] VAT Return Preparation has its own authenticated named route and Tax Compliance-grouped Livewire page/template using the shared shell and existing form-style appearance.
- [x] Livewire interactions preserve the current selectable reporting periods, editable company-name field, displayed preparation information, and illustrative taxable/VAT/total values from the established fixtures.
- [x] The page and printed document explicitly state that this is an illustrative preparation interface, not a complete/official Form 2550Q and not a filing or submission. Keep the existing no-eFPS/eBIRForms-replacement limitation visible.
- [x] Printing isolates the current preparation document and selected values from navigation, other reports, and receipt print styles.
- [x] Do not add official-return generation, statutory rules, a submission service, business persistence, or automatic ingestion of POS demo sales.
- [x] Smoke period changes, company-name editing, direct entry, guest protection, and browser print preview, confirming selected values and demonstration limitations appear in print.
