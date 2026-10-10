# 21: Close and dependency-check reopening of accounting months

**What to build:** An accounting administrator can close reconciled Manila months and explicitly reopen affected dependent periods without silently changing later reports marked final.

**Blocked by:** 16: Generate the corporate Balance Sheet without double-counted earnings.

**Status:** ready-for-agent

- [ ] Show monthly period state and readiness; closing requires exact balanced books, matching AP/Inventory schedules and Assets = Liabilities + Equity.
- [ ] A closed month rejects every journal, source posting, opening/correction, disbursement and valuation path at execution; drafts may remain without affecting the closed balances.
- [ ] Separate close and reopen authority; record actor, time and reason. Normal corrections use the current open period.
- [ ] Reopening an earlier month requires explicit authorization for all later closed dependent periods, never a hidden cascade; show the dependent chain and require chronological reconciliation/reclosing.
- [ ] Preserve forward-only inventory chronology when a month reopens; reopening does not rewrite old valuation or posted source history.
- [ ] Affected fiscal closing entries, when present, must be reversed before correction and recreated only after reconciliation; the fiscal-closing slice completes and verifies that interaction.
- [ ] Demonstrate closing a reconciled month, rejecting a posting into it, and rejecting incomplete/unauthorized reopening of a later-dependent chain; cover mismatch/error conditions and all posting-path guards.
