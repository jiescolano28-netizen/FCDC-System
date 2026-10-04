# 07: VAT Records

**What to build:** A standalone VAT Records demonstration page with searchable/filterable illustrative transactions and a working record-detail modal. Establish the existing illustrative VAT data for reuse by the other Tax Compliance pages.

**Blocked by:** 01: Settings and shared application shell.

- **Status:** completed
- [x] VAT Records has its own authenticated named route and Tax Compliance-grouped Livewire page/template using the shared shell and current appearance.
- [x] Existing illustrative references, dates, periods, taxable amounts, VAT amounts, and totals remain demonstrations; they are not imported into business tables or represented as actual sales.
- [x] Livewire reference/period search, tax-period filtering, transaction-date filtering, combined filters, record details, and closing the detail modal select/show the expected fixture records. Preserve usable empty results and the existing count presentation without adding an unrelated paging workflow.
- [x] Share the existing fixture data and reporting-period conventions with later VAT pages without mounting those pages or depending on global browser variables.
- [x] Visible demo/non-filing limitations prevent this page or its Tax Compliance module label from claiming statutory compliance. POS demo sales do not silently become VAT transactions.
- [x] Smoke filters individually and together, known record details, empty results, direct entry, and guest protection in the actual browser. No real tax or sales record is created.
