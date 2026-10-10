# 01: Approve the chart of accounts and posting mappings

**What to build:** Authorized accounting staff can maintain and approve real accounts and source-account mappings; demonstration accounts never become production records.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [x] Create/search/filter persisted accounts with unique code, name, description, active/inactive status, one of the five account types, normal balance and approved statement classification; COGS is classified within Expense.
- [x] Authorized reviewers approve accounts and Cash/Bank, card clearing, Sales, Output VAT, COGS, Inventory, AP, recovery and adjustment mappings before use; changed mappings require renewed approval.
- [x] Used account code/type/classification remain historically stable, used accounts cannot be deleted, and deactivation retains balances and posted identification while preventing new posting.
- [x] Enforce separate view, maintenance and approval capabilities on the server; do not import demonstration reference accounts or journal history.
- [x] Exercise account creation, approval, deactivation and a rejected unauthorized or historical-classification change through the actual UI; retain deterministic behavior coverage for approval and immutability boundaries.
