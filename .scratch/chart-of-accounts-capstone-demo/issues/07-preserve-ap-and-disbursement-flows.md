# 07: Preserve AP and disbursement accounting flows

**What to build:** Supplier purchases, AP settlements, direct disbursements, and their corrections retain their existing accounting roles and traceability; payments do not recognize purchases twice, and restricted Miscellaneous Expense allocations fail safely.

**Blocked by:** 02: Seed the complete proposed chart safely; 03: Preserve safe account maintenance and approval; 04: Enforce special-account accounting treatment; 05: Block Miscellaneous Expense until its policy is approved

**Status:** implementation-complete

- [x] Supplier purchases and AP settlements preserve their distinct accounting roles; settlement reduces outstanding AP without recognizing the purchase again.
- [x] Direct disbursements and AP settlements retain existing approvals, source links, and actual authorized actors.
- [x] Supplier purchase allocations, supplier purchase corrections, and cash disbursement allocations enforce account eligibility and reject prohibited 6190 use server-side.
- [x] Failed operations leave supplier, AP, cash, journal, and audit records unchanged at the relevant atomic boundary.

**Verification:** `php artisan test --compact tests/Feature/SupplierPurchaseInvoicesTest.php tests/Feature/SupplierPaymentAllocationsTest.php tests/Feature/CashDisbursementsTest.php` — 22 passed, 330 assertions. Coverage confirms supplier purchase recognition posts expense/inventory against AP once; settlement debits AP and credits cash/bank without purchase-recognition lines; distinct preparer/poster actors and source links are retained; restricted 6190 attempts and injected journal failures preserve source and accounting state.
