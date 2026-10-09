# 02: Stock-out and counted-balance adjustment

**What to build:** Authorized staff can issue stock and reconcile the ledger to a physical count, with history that explains each movement and prevents negative chronological balances.

**Blocked by:** #01 Ledger-backed Inventory items and stock-in.

**Status:** ready-for-agent

- [x] Authorized staff can record stock-out against an active Inventory item; a stock-out is rejected if the resulting chronological balance would become negative.
- [x] Authorized staff can record a stock adjustment by entering the physically counted on-hand quantity; the system records the difference as an immutable adjustment transaction.
- [x] Stock-out and adjustment require a reason category selected from purchase/receipt, project use, sale, return, damage/loss, count correction, or other; notes and a free-text reference are optional.
- [x] Effective dates can be backdated; the actual posting time remains separately visible, and the ledger rejects any backdated movement that would make a later balance negative.
- [x] Stock-out and adjustment appear in Inventory item history and update the displayed on-hand quantity.
- [x] Posted stock-out and adjustment records cannot be edited or deleted; corrections use linked reversal and reposting transactions.
- [x] Recording these movements requires the separate movement-recording permission.
