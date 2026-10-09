# 04: Prepare a quarterly VAT worksheet for 2550Q review

**What to build:** Tax-authorized staff can select a year and calendar quarter, review a live VAT preparation worksheet and its supporting transactions, download a summary CSV or print/save a preparation copy. Consume the same recorded-period calculation as VAT Summary. This is a POS-only preparation aid, not an official Form 2550Q replica or BIR submission.

**Blocked by:** 02: Summarize recorded VAT by month and quarter. Supporting transaction review uses the VAT Records behavior delivered by ticket 01; this ticket does not depend on the report/export screen in ticket 03.

**Status:** ready-for-agent

- [ ] The worksheet initially selects the current Manila calendar quarter and supports an explicit year and Q1-Q4 selection using calendar-quarter boundaries.
- [ ] Populate taxable sales, recorded output VAT and VAT-inclusive totals from the same recorded-period calculation used by VAT Summary, with completed-sale dates interpreted in Asia/Manila.
- [ ] Worksheet figures reconcile with VAT Records and VAT Summary for the selected quarter, including transaction-level rounding and the Manila September/October boundary.
- [ ] Clearly display input VAT as not captured, deductions applied as PHP 0.00 and the resulting POS-only VAT payable estimate. Do not imply the company has no actual input VAT or that the estimate is its final VAT liability.
- [ ] Staff can inspect the quarter's supporting transactions and saved sale details. Review does not allow overwriting amounts, editing/deleting VAT records or entering input VAT.
- [ ] The worksheet is live and read-only. Display its generation time and explain that additional completed sales can change a period's figures. Do not introduce approval status, persisted reviewed snapshots or period locking.
- [ ] Download a summary CSV containing the selected year/quarter, taxable sales, output VAT, VAT-inclusive total, input-VAT capture status, deductions applied, payable estimate, reporting timezone, generation time and POS-only scope.
- [ ] Provide a print-friendly preparation copy with the same figures and scope. PDF saving uses the browser print dialog, not a direct application-generated PDF download.
- [ ] Use Fabellion Construction and Development Corp. as the established company name. Do not invent TIN, registration or address information from demonstration settings.
- [ ] The visible worksheet and exported/printed preparation copy clearly state that they are not an official Form 2550Q, a complete official return or a submitted filing. No official-form field mapping, BIR submission or implied filing confirmation is offered.
- [ ] Empty quarters show zero totals and no recorded POS sales rather than demonstration figures. Worksheet, supporting-detail and CSV access require tax.view.
- [ ] Behavioral regression coverage proves quarter selection, shared-total reconciliation, authorization and changes from additional recorded sales. Exercise the actual worksheet, supporting-record review, summary CSV download and browser print/Save as PDF output.
- [ ] Replace demonstration preparation data and update affected current-state documentation. Remove the obsolete VAT demonstration source only after no tax screen consumes it; do not leave shared stale imports or delete fixtures still needed by an unfinished parallel slice.
