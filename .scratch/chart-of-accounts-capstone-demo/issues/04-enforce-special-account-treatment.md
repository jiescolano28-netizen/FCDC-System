# 04: Enforce special-account accounting treatment

**What to build:** Financial statements represent contra accounts with the specified signs, derived earnings appear only once, and unresolved proposal treatments cannot be made postable merely by ordinary account approval.

**Blocked by:** 03: Preserve safe account maintenance and approval

**Status:** implementation-complete

- [x] In isolated approved test scenarios, credit-balance 1510 reduces net assets, debit-balance 3050 reduces equity, and debit-balance 4090 reduces net revenue without absolute-value or normal-balance transformations.
- [x] Account 3040 remains visible but cannot be approved for posting or posted directly; financial statements exclude ledger balances for it and retain one derived unclosed-earnings figure. Fiscal-year close behavior remains unchanged.
- [x] POS posts Output VAT once; 2040 is not mapped or used to infer settlement, keeping POS VAT estimates distinct from company ledger liabilities.
- [x] 5030 remains non-postable pending policy; receipt uploads leave inventory carrying value and journals unchanged, and stock-adjustment reasons remain distinct from shrinkage.
- [x] Pending policy restrictions remain authoritative through ordinary approval and direct posting-service entry points.
