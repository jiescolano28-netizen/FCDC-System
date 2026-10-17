# 01: Add adjusting entries and the unadjusted / adjusted Trial Balance

**What to build:** Manual journals can be flagged as adjusting entries. The Trial Balance
presents unadjusted balances, the selected period's adjusting entries, and adjusted
balances that reconcile with the General Ledger and act as the financial-statement basis.

**Blocked by:** none

**Status:** resolved

- [x] `accounting_journals.entry_type` (`standard` | `adjusting`) added via migration with a `standard` default that preserves existing rows.
- [x] `ManualJournalService` validates and persists `entryType`; corrections carry the adjusting classification onto the linked reversal and replacement.
- [x] Journal Entry component and view expose an entry-type selector, an entry-type filter, and an "Adjusting entry" register badge.
- [x] `TrialBalance` computes opening, standard period movement, unadjusted, adjustments, and adjusted debit/credit pairs and totals without a balancing plug.
- [x] Trial Balance view shows the unadjusted/adjusting/adjusted worksheet and states the adjusted trial balance is the basis for the financial statements.
- [x] Coverage added in `TrialBalanceTest` and `JournalEntryTest`, including period-separation and pre-period adjustment behavior.
