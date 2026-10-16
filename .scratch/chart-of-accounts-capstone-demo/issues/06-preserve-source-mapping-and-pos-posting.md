# 06: Preserve approved source mapping and POS posting

**What to build:** Existing approved source mappings continue to route POS activity to its approved destination exactly once. New proposal accounts do not change routing cardinality or make an unmapped sale complete.

**Blocked by:** 02: Seed the complete proposed chart safely; 03: Preserve safe account maintenance and approval; 04: Enforce special-account accounting treatment

**Status:** implemented

- [x] Existing authorized mapping and approval workflows remain separate from account approval and preserve source references and audit attribution.
- [x] POS sale posting remains atomic and exactly once with its existing approved mapping; retries do not duplicate journals or stock effects.
- [x] Missing, inactive, incompatible, or unapproved mappings/accounts block source completion without selecting a proposed account or Miscellaneous Expense as fallback.
- [x] Adding 4010/4020, 5010/5020, or 1050/1060 does not introduce category-aware routing; any split-routing or card-settlement-account need is reported separately.
- [x] POS Output VAT is posted once, with no automatic 2040 mapping or duplicate liability.
