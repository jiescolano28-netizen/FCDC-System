# 03: Generate, export and print POS-sourced VAT reports

**What to build:** Tax-authorized staff can generate a POS-sourced VAT report for a month, calendar quarter or custom inclusive date range, inspect its matching transactions and download or print the report. Use the recorded-period calculation delivered by VAT Summary so displayed and exported figures reconcile with recorded business activity.

**Blocked by:** 02: Summarize recorded VAT by month and quarter.

**Status:** ready-for-agent

- [ ] The report initially selects the current Manila calendar month and supports selecting a year/month, year/calendar quarter or custom inclusive date range.
- [ ] Results contain only recorded POS-sourced VAT activity matching Asia/Manila completion-date boundaries. Include the whole selected end date; preserve UTC storage and the existing application timezone.
- [ ] Taxable sales, output VAT and VAT-inclusive totals reconcile with VAT Records and VAT Summary for identical dates. Preserve transaction-level rounding, including PHP 43.22 VAT for two PHP 180.05 taxable sales.
- [ ] Staff can inspect matching transaction details, including the source number, completion date, customer where recorded, item lines and saved amounts, without mutating business records.
- [ ] Transaction-level CSV exports include every matching transaction across pagination, not just the visible page. Exported rows and report totals reconcile to the same selected scope and amounts.
- [ ] CSV output identifies its selected reporting period/range, Asia/Manila timezone, generation time and POS-only coverage, including when the selected period has no transactions.
- [ ] Print-friendly output preserves the selected period/range, transaction details and totals, timezone, generation time and POS-only scope. Browser printing supports Save as PDF; direct application-generated PDF downloads are not required.
- [ ] Use the established company name, Fabellion Construction and Development Corp. Do not invent TIN, registration or address information from demonstration settings, or present the output as an official return or submitted filing.
- [ ] Empty selections show zero totals and no recorded POS sales rather than sample report content.
- [ ] Viewing, transaction-detail access and CSV download are consistently authorized by tax.view. Report printing is available only from an authorized report surface.
- [ ] Behavioral regression coverage proves inclusive date boundaries, financial reconciliation, authorization and complete multi-page CSV exports. Exercise the actual report, download a filtered CSV and inspect its contents, and inspect print output using browser print/Save as PDF.
- [ ] Replace demonstration report data and update affected documentation. Keep the preparation screen accurately described if its parallel ticket is unfinished. If this is the final tax-screen cutover, remove the obsolete shared VAT demonstration source and stale imports; otherwise preserve the source for its remaining consumers.
