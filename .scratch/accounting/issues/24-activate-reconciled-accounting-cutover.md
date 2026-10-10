# 24: Activate the reconciled accounting cutover end to end

**What to build:** An authorized operator can activate complete accounting only after real approvals and intervening source history reconcile, with one coherent production cutover and no duplicate operational records.

**Blocked by:** 17: Show the real Accounting Overview and quick actions; 22: Close fiscal years and preserve reported operating income; 23: Reconcile sources between cutover and activation.

**Status:** implemented

- [x] Require actual approved chart/mappings, cutover, balanced opening journal, matching supplier/item schedules, recovery/adjustment policies and any required YTD summary evidence; surface missing prerequisites rather than invent company data.
- [x] Require successful reconciled inclusion of intervening sources before activation; uncaptured costs, unmatched schedules, duplicate source links or incomplete approval block activation.
- [x] Verify every completion/correction path participates in approved source-linked atomic posting and closed-period/permission controls, including direct purchases, recovered material and purchase-VAT adjustments.
- [x] Enable real overview/reports only after successful authorized activation; retain truthful unavailable states before it and genuine zero/no-activity states afterward. Activation/inclusion retry must not duplicate posted effects.
- [x] Remove obsolete accounting demonstration fixtures/session callers and inaccurate current-state accounting claims as part of the clean production cutover; preserve unrelated parent tickets and historical operational records.
- [x] Exercise a complete authorized UI flow from received purchase through sale, partial payment, financial correction, VAT reclassification, GL/TB/statements and period closing; also demonstrate rejected incomplete activation.
- [x] Retain deterministic end-to-end coverage for source rollback, schedule reconciliation and closing dependencies; update user-facing accounting documentation without claiming legal/audit certification.
