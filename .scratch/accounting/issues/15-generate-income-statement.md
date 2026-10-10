# 15: Generate the classified Income Statement

**What to build:** An accountant can calculate period income/loss from approved account classifications, including clearly identified approved pre-cutover YTD summaries when the requested report supports them.

**Blocked by:** 13: Browse the posted General Ledger with running balances.

**Status:** ready-for-agent

- [ ] Show VAT-exclusive Sales minus COGS as Gross Profit, then operating expenses and separately classified other income/expenses to derive Net Income/Loss with the correct sign.
- [ ] Use posted activity and approved classifications, not names/account-code guesses; omit drafts and tax from revenue.
- [ ] Exclude ordinary opening balances and fiscal closing entries/their reversals from operating results; include approved pre-cutover nominal-account summaries only for eligible YTD/year coverage.
- [ ] Label pre-cutover summaries and reject unsupported arbitrary earlier date ranges instead of fabricating historical detail.
- [ ] Display selected inclusive Manila period, scope and generation time; enforce report-view permission and distinguish unavailable/unapproved from genuine zero activity.
- [ ] Demonstrate a period with Sales, COGS, operating and other expenses/income yielding an exact profit or loss, plus supported versus unsupported pre-cutover coverage.
