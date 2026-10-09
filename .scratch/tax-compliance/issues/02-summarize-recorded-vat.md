# 02: Summarize recorded VAT by month and quarter

**What to build:** Tax-authorized staff can select a year and calendar month or quarter and see VAT Summary figures reconciled to recorded VAT activity. Show taxable sales, output VAT, VAT-inclusive total sales and a clearly scoped POS-only VAT payable estimate. Establish one reusable recorded-period calculation for the report and preparation worksheet slices, rather than separate financial formulas on each screen.

**Blocked by:** 01: Automatically record and browse POS VAT.

**Status:** ready-for-agent

- [ ] Monthly and quarterly selectors use recorded VAT activity for the selected year and period. Calendar quarters are January-March, April-June, July-September and October-December.
- [ ] Period boundaries use the source sale's completed timestamp in Asia/Manila and the date semantics established by VAT Records. A September 30 sale at 16:30 UTC belongs to October and Q4, not September or Q3.
- [ ] Taxable sales, output VAT and VAT-inclusive totals are sums of saved record amounts. No recalculation on the aggregate taxable subtotal or current settings changes the recorded figures.
- [ ] Two taxable sales of PHP 180.05 each, with PHP 21.61 recorded VAT each, yield PHP 360.10 taxable sales, PHP 43.22 output VAT and PHP 403.32 total. Explain that checkout-level rounding can differ from applying 12% once to the aggregate.
- [ ] Display input VAT as not captured and deductions applied as PHP 0.00. Do not imply that unavailable purchase information proves the company incurred no input VAT.
- [ ] The POS-only VAT payable estimate equals recorded output VAT minus deductions applied; with no deductions supported, it equals recorded output VAT. It is not labeled the company's final VAT liability.
- [ ] The period calculation can be reused for monthly, quarterly and custom inclusive date-range reporting, so later outputs consume the same recorded amounts and Manila boundaries.
- [ ] An empty period shows zero totals and an explicit no-recorded-POS-sales state, without demonstration summaries or invented financial activity.
- [ ] Summary access requires tax.view, and the screen clearly identifies its selected period and POS-only coverage. No manual amount editing, input-VAT entry or period locking is offered.
- [ ] Behavioral regression coverage proves quarter/year boundaries, centavo reconciliation and correct empty-period totals. Exercise the actual monthly and quarterly summary surface and compare its figures with the corresponding VAT records.
- [ ] Replace demonstration data on VAT Summary and update affected current-state documentation; do not claim the remaining report or preparation screens are already functional.
