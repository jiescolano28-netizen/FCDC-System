# 19: Correct valued stock and recovery assessments safely

**What to build:** Authorized staff can correct recovery or non-sale stock errors through linked current-period effects while preserving accepted/consumed history and consistent quantity/value/GL schedules.

**Blocked by:** 07: Value accepted recovered materials.

**Status:** ready-for-agent

- [x] Extend the existing reversal/reassessment history with approved value/counterpart and journal links; retain original assessment, project provenance, accepted/rejected quantities and correction reason.
- [x] Separate physical corrections from value-only corrections; value-only changes never change quantity, and consumed stock cannot be made to disappear by reversing the original receipt.
- [x] Require approved remaining/consumed-value treatment where downstream movements exist; reject unsupported negative quantity/value or a correction exceeding its permitted source/remaining amount.
- [x] Create new forward-only effective movements and linked balanced entries in an open period; do not replay/rewrite old sale costs or bypass chronology by reopening a month.
- [x] Commit correction assessment, physical effects when authorized, valuation and GL together; enforce action/valuation permissions and record actor/history.
- [x] Demonstrate reassessing a partially accepted recovery and correcting value after consumption, plus rejected unavailable-stock reversal; cover atomicity and preserved original costs.
