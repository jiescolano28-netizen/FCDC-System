# 04: Recovered material assessment and receipt

**What to build:** Staff can record material recovered from a Demolition Project, assess its condition, accept or reject quantities, and automatically receive accepted quantities into Inventory with a traceable stock transaction.

**Blocked by:** #01 Ledger-backed Inventory items and stock-in; #03 Demolition Project source records.

**Status:** done

- [x] A recovered-material record identifies its Demolition Project, material, recovered quantity/unit, condition (good, fair, or poor), and optional notes.
- [x] Staff can accept and reject portions of a recovered quantity; accepted plus rejected quantities reconcile to the recovered quantity, and rejected quantities require a rejection reason.
- [x] At assessment/acceptance, staff select an existing Inventory item or create one; the recovered unit must exactly match the Inventory item's unit.
- [x] Accepting material automatically posts an immutable stock-in for the accepted quantity, linked to the project and recovery record; rejected quantity does not affect on-hand inventory.
- [x] Accepted recovered material follows the same POS availability rules as other Inventory items.
- [x] An accepted recovery cannot be edited in a way that bypasses its stock transaction; correction uses reversal and reassessment with linked stock history.
- [x] Recovery/project actions are gated by the separate recovery/project permission, and any resulting stock movement is recorded in the Inventory ledger.
