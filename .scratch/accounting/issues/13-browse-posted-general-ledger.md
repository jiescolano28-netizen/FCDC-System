# 13: Browse the posted General Ledger with running balances

**What to build:** Accounting staff can inspect posted account activity and its opening/running balances, following each line back to its journal or operational source without encoding a separate GL.

**Blocked by:** 03: Post and correct persistent manual journals.

**Status:** ready-for-agent

- [ ] Select an approved account and inclusive Manila date range; display date, journal/source reference, description, debit, credit and running balance with actual debit/credit side.
- [ ] Read posted journal lines only, including openings and linked corrections; derive opening balance from all earlier posted activity and apply in-range lines in deterministic order.
- [ ] Preserve inactive accounts with relevant history and correctly show normal, unusual and contra balances; an empty selected period can still have a nonzero opening/closing balance.
- [ ] Source links resolve journal and operational detail when that source exists; extending source types does not require copying transactions into another GL table or pretending missing sources exist.
- [ ] Enforce view permission, supported historical coverage and full inclusive end-date filtering; never substitute demonstration/session entries.
- [ ] Demonstrate an opening balance plus a later journal/reversal and an activity-free month; cover date boundaries, deterministic running balances and unauthorized access.
