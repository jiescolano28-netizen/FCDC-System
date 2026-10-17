# 02: Build the Notes to Financial Statements / Disclosure module

**What to build:** Replace the disclosure stub with a working module that generates
figures from the adjusted trial balance, records reviewed explanatory notes, separates
generated from manual content, and protects finalized disclosures.

**Blocked by:** 01

**Status:** resolved

- [x] `accounting_disclosure_notes` migration and `AccountingDisclosureNote` model with status/finalization guards.
- [x] Generated figures (cash and cash equivalents, accounts receivable, inventory, accounts payable, revenue) derived from the adjusted trial balance for the reporting period.
- [x] Generated notes capture source metric, reporting period, and amount snapshot; manual notes are labeled separately.
- [x] Review lifecycle (draft → pending review → approved → finalized) with separate `accounting.manage-disclosures` and `accounting.approve-disclosures` permissions and preparer/approver separation.
- [x] Finalized disclosures protected from editing and deletion; content immutable after submission.
- [x] Printable page with an explicit no-audit/no-CPA-certification disclaimer.
- [x] Coverage added in `NotesToFinancialStatementsTest`.
