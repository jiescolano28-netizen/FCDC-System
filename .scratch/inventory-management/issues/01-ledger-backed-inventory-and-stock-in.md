# 01: Ledger-backed Inventory items and stock-in

**What to build:** Inventory staff can manage Inventory items, receive stock, and inspect the resulting on-hand balance and history. Existing quantities remain accurate as opening-balance stock transactions, and POS availability respects item lifecycle status.

**Blocked by:** None (can start immediately).

**Status:** complete

- [x] New Inventory items receive unique sequential `INV-` codes; name, category, and unit are required; description and image are optional; unit cost and selling price remain separate values.
- [x] Inventory items have active/inactive lifecycle status; inactive items cannot receive new stock movements or be sold through POS, and inventory items are deactivated rather than deleted.
- [x] Existing on-hand quantities are preserved as opening-balance stock transactions; later on-hand quantity is derived from stock transactions rather than direct edits in Inventory Master.
- [x] Authorized staff can record stock-in with a required reason category, optional notes/reference, an effective date, and a separately recorded posting time; the transaction appears in item history and updates on-hand quantity.
- [x] Posted stock transactions are immutable; a correction is represented by reversal and reposting, not by editing or deleting the original transaction.
- [x] Inventory viewing and movement recording are separately permission-gated; item-level unit cost is not automatically recalculated by stock-in.
- [x] POS continues to offer only active, positively stocked items with a selling price.
