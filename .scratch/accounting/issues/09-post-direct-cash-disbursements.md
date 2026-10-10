# 09: Post direct cash, bank and released-check disbursements

**What to build:** Staff can pay a direct non-inventory expense or asset purchase through Cash Disbursements, with traceable evidence and the correct Cash/Bank effect rather than a fictitious AP invoice.

**Blocked by:** 03: Post and correct persistent manual journals.

**Status:** ready-for-agent

- [ ] Capture payee, payment/release date, approved method, amount, actual Cash/Bank account, reference, description and supporting document or reference; require non-cash reference and check number where applicable.
- [ ] Support draft preparation/edit/delete and posted search/filter/detail; drafts change no cash, accounts or stock. Allocate direct payments across approved non-control debit accounts, exactly matching the payment credit.
- [ ] Posting debits actual acquired asset/expense accounts and credits the selected approved money account atomically; method labels alone never determine the account.
- [ ] Prepared/unreleased checks stay drafts. Post on release to the payee, not bank clearance; a voided/returned released check uses a reasoned linked reversal, retaining the original.
- [ ] Only explicitly approved Other methods are accepted; validate authority, active mappings, dates and open periods at execution.
- [ ] Do not add a bank-clearing, supplier-advance or receiving module. Inventory payments become available only through their later linked-valued-purchase slice.
- [ ] Demonstrate a PHP 2,000 direct utility payment and a released-check reversal; cover allocation inequality, missing evidence/reference, permissions and rollback.
