# 02: Review the cutover and opening accounting books

**What to build:** An accountant can establish a January–December fiscal-year cutover, review balanced opening account balances and pre-cutover YTD summaries, and see exactly which approvals or supporting schedules are still missing.

**Blocked by:** 01: Approve the chart of accounts and posting mappings.

**Status:** ready-for-agent

- [ ] Persist the approved cutover date, opening journal and approval identity; use Manila business dates and preserve established UTC timestamp storage.
- [ ] Opening lines use approved accounts, PHP centavos and exact debit/credit equality; reject empty, negative, unbalanced or duplicate opening posting and preserve posted opening history.
- [ ] Support approved current-year income/expense opening summaries for midyear cutover, distinctly identified from ordinary opening balances and recorded operational activity.
- [ ] Expose readiness and coverage to the accountant: missing chart approval, supplier/item reconciliation, valuation policies or YTD evidence prevents production activation rather than implying zero or inventing values.
- [ ] Opening AP/Inventory require their controlled supporting schedules; until those workflows are available, no unrestricted control-account opening can be approved.
- [ ] Establish the common persisted posting-period, source identity, actor and immutable journal rules through this opening-books path, not an unrelated horizontal infrastructure ticket.
- [ ] Demonstrate a balanced cash/capital opening and rejection of an unequal opening in an isolated approved book; cover duplicate posting and invalid approval boundaries. Production activation remains gated by the final cutover ticket.
