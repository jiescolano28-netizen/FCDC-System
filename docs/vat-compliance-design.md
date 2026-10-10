# VAT compliance workflow design

Status: confirmed design; not implemented by this document.

## Scope

VAT is the functional tax workflow for this scope. All supported POS sales are standard-rated at 12% on VAT-exclusive selling amounts. Only recorded POS sales are covered; reports and worksheets must not imply coverage of all company taxable activity or determination of the company's final VAT liability.

VAT-exempt and zero-rated sales, non-POS sales, purchase-based input VAT, accountant-entered input VAT, cancellations, refunds and reversals are outside this scope. The application does not submit returns to BIR.

## 4.1 VAT Records

- Each successful checkout creates exactly one immutable VAT record linked to its completed POS transaction, within the same database transaction as the sale and stock changes.
- Failed checkout creates neither a completed sale nor a VAT record. A VAT record must not exist without its source sale; enforce uniqueness of the source POS transaction.
- Copy the sale's saved taxable subtotal, VAT rate, VAT amount and VAT-inclusive total. The source POS transaction and VAT record must reconcile exactly; do not recalculate historical amounts using current settings.
- Include all existing recorded POS sales using their original saved amounts and completion timestamps. Create missing linked records without duplicating existing ones. Demonstration fixtures are never migrated into business records.
- Preserve the actual POS transaction number. `S-1050` is an illustrative example, not a requirement to change the existing numbering scheme.
- View and search records by a case-insensitive substring of the transaction number; filter by an inclusive date range.
- Record details include the source transaction number, Manila completion date/time, customer where recorded, item lines, quantities, selling amounts, VAT and totals. There are no VAT-record edit or delete actions.

Example: a taxable sale of PHP 180.00 produces VAT of PHP 21.60 and a VAT-inclusive total of PHP 201.60. The linked VAT record carries those same amounts.

## 4.2 VAT Summary

- Support monthly and calendar-quarter summaries for a selected year and period.
- Report total taxable sales, output VAT, VAT-inclusive total sales, input-VAT capture status, deductions applied and the POS-only VAT payable estimate.
- Sum saved transaction amounts. Checkout rounds VAT to centavos; period output VAT is the sum of the recorded VAT, not a fresh calculation on the period subtotal.
- The simplified 12% formula governs sale calculations. Aggregate rounding can differ from 12% of combined taxable sales: two sales of PHP 180.05 each record PHP 21.61 VAT, totaling PHP 43.22, while one calculation on PHP 360.10 rounds to PHP 43.21. Reports retain PHP 43.22 to reconcile with the sales.
- Display `Input VAT not captured; deductions applied: PHP 0.00`. Do not represent unavailable purchase information as proof that actual company input VAT is zero.
- Calculate the POS-only VAT payable estimate as recorded output VAT minus deductions applied. With no input-VAT deductions supported in this scope, the estimate equals recorded output VAT.

## Reporting dates and empty periods

- Use completed-sale timestamps converted to `Asia/Manila` for dates, months, quarters and inclusive date-range filters. Preserve UTC storage and the existing application timezone; reporting conversion does not require changing the global timezone.
- Calendar quarters are Q1 January-March, Q2 April-June, Q3 July-September and Q4 October-December.
- A completion timestamp of September 30 at 16:30 UTC is October 1 at 00:30 in Manila and belongs to Q4, not Q3.
- Include the entire selected end date in Manila time. Do not truncate an inclusive end date at its start.
- Empty periods show zero totals and `No recorded POS sales`. Never substitute demonstration data.
- Record and report pages initially show the current Manila calendar month. The preparation worksheet initially shows the current Manila calendar quarter.

## 4.3 Tax Report

- Generate a POS-sourced VAT report for a month, calendar quarter or custom inclusive date range.
- Show matching transaction details and totals that reconcile with VAT Records and VAT Summary for the same scope and dates.
- Download a transaction-level CSV containing all matching transactions, not merely the displayed pagination page.
- Provide a print-friendly report; PDF saving uses the browser print dialog. Direct application-generated PDF downloads are not required.
- Clearly identify the selected reporting period/range, Asia/Manila timezone, generation time and POS-only coverage.

## 4.4 VAT Return Preparation - 2550Q

- Select year and calendar quarter; show a live, read-only VAT preparation worksheet.
- Populate taxable sales, recorded output VAT, input-VAT capture status, deductions applied and the POS-only VAT payable estimate from the same records used by the reports and summaries.
- Review means inspecting the worksheet and supporting transactions before export or printing. Users cannot manually overwrite its amounts.
- Download a summary CSV or print/save the preparation copy as PDF through the browser.
- The worksheet is not an official Form 2550Q replica, a complete official return or a submitted filing. No official-form field mapping or BIR integration is included.
- No review approval status, persisted reviewed snapshot, period lock or saved-copy workflow is included. A live worksheet can change when additional sales are completed; generation time identifies when a displayed/exported copy was produced.

## Access and company identity

- Preserve the existing `tax.view` permission for tax records, summaries, details, worksheets, CSV downloads and printing. POS checkout remains governed by `pos.checkout`; automatic record creation does not require the cashier to hold `tax.view`.
- Use the established company name, Fabellion Construction and Development Corp. Do not invent TIN, registration or address information from demonstration settings.
- Outputs identify their POS-only scope and are preparation/reporting aids, not claims of statutory completeness.

## Acceptance scenarios

1. Complete the PHP 180.00 example sale: the saved sale and its single linked VAT record both show PHP 180.00 taxable sales, PHP 21.60 VAT and PHP 201.60 total.
2. Fail checkout: no completed transaction or VAT record is persisted.
3. Include pre-cutover recorded POS sales: saved historical amounts and dates are preserved, demonstration fixtures are excluded, and repeating the inclusion operation creates no duplicates.
4. Attempt to change or delete a completed sale's VAT record: the immutable record remains unchanged.
5. Search by part of a transaction number and filter an inclusive Manila date range: only matching recorded sales appear, including sales throughout the end date.
6. Use the October 1 Manila boundary example: the sale appears in October and Q4, not September or Q3.
7. Use the two PHP 180.05 sales: all corresponding records, summaries, reports and worksheets reconcile to PHP 43.22 output VAT.
8. Select an empty period: show zero recorded totals and an explicit no-sales state without sample records.
9. Apply identical dates across records, summaries, reports and preparation: their recorded taxable sales, VAT and totals agree.
10. Export a multi-page filtered report: its CSV contains all matching transactions; printing preserves the period, scope, timezone and generation time. The worksheet offers its summary CSV and printable preparation copy.
11. View the worksheet: input VAT is explicitly not captured, deductions applied are zero, and the payable figure is labeled a POS-only estimate. No filing, manual amount editing, review approval or period locking is offered.
12. Authorize tax page/detail/export access consistently with `tax.view`, while permitting successful authorized POS checkout to create its VAT record without tax-page access.

## Repository evidence at design time

- `app/Livewire/Pos/PointOfSale.php` persists completed sales and stock changes atomically, calculates 12% VAT, and saves subtotal/VAT/total snapshots.
- `app/Models/PosTransaction.php` rejects updates and deletion of completed sales.
- At design time, the four `app/Livewire/TaxCompliance` components read `app/Support/VatDemonstrationData.php`, not persisted POS records.
- No persisted purchase-invoice/input-VAT source or POS refund/void workflow was found in the inspected application paths.
- `php artisan about --only=environment` confirmed the application timezone is UTC. A read-only `php artisan tinker` scenario confirmed the Manila date conversion and the transaction-versus-aggregate rounding difference.

These observations describe the repository at design time. VAT Records and the recorded monthly/quarterly VAT Summary are now implemented; Tax Report and VAT Return Preparation remain illustrative/demo-backed. Current implementation status is maintained in `docs/livewire-setup-and-structure.md`.
