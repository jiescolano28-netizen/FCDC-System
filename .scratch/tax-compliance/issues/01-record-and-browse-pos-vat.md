# 01: Automatically record and browse POS VAT

**What to build:** Every successful POS checkout automatically creates one immutable VAT record linked to the completed POS transaction. Tax-authorized staff can browse recorded VAT activity, search transaction numbers, filter Manila dates and inspect supporting sale details. Include all previously recorded POS sales without introducing demonstration fixtures or duplicating records. Follow the accepted decision to persist a separate VAT record whose amounts reconcile exactly with its source sale.

**Blocked by:** None (can start immediately). The prerequisite persisted POS checkout is already completed.

**Status:** completed

- [x] A standard-rated sale with PHP 180.00 taxable sales produces exactly one linked VAT record containing PHP 21.60 output VAT and PHP 201.60 VAT-inclusive total. Preserve the existing POS numbering scheme; example numbers do not require renumbering.
- [x] VAT record creation participates in the same atomic checkout as the completed sale and stock changes. Failed checkout, including a failure to persist its VAT record, leaves no partial sale, VAT record or stock deduction.
- [x] A VAT record cannot exist without its source sale, and each source POS transaction can have at most one VAT record. Copy its saved taxable subtotal, 12% VAT rate, VAT amount and total; do not recalculate historical amounts using current settings.
- [x] All previously recorded completed POS sales receive missing VAT records using their saved amounts and original completion timestamps. Repeating historical inclusion does not create duplicates or change already recorded amounts. Never include demonstration fixtures.
- [x] VAT records are immutable and expose no edit/delete actions. Updating or deleting a VAT record cannot change the recorded tax activity.
- [x] VAT Records initially displays the current Manila calendar month. A case-insensitive transaction-number substring search and an inclusive date-range filter restrict the results correctly, including sales throughout the selected end date.
- [x] Completion timestamps remain stored in UTC and are interpreted in Asia/Manila for reporting without changing the global application timezone. A September 30 completion at 16:30 UTC appears on October 1 in Manila.
- [x] Details show the source transaction number, Manila completion date/time, customer where recorded, item lines, quantities, selling amounts, VAT and totals. Changes to current inventory details do not alter the saved sale details.
- [x] Displayed records are actual POS-sourced business activity, not company-wide tax results. An empty selection shows no recorded sales rather than sample records.
- [x] Records and their detail actions require tax.view. A cashier authorized for pos.checkout can complete a sale and create its VAT record without holding tax.view.
- [x] Behavioral regression coverage proves atomic rollback, one-record-per-sale enforcement, immutable amounts, duplicate-free historical inclusion and Manila date/search boundaries. Exercise checkout and the actual records/detail surface as runtime verification.
- [x] Update the affected current-state documentation to reflect the functioning VAT Records workflow while keeping unconverted tax screens accurately identified as demonstration-backed. Refunds, reversals, exempt/zero-rated sales, non-POS sales and input-VAT entry remain outside scope.
