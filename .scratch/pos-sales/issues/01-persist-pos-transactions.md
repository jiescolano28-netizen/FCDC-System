# 01: Complete persisted POS transactions

**What to build:** Cashiers can find inventory items by search or category, build a cart with quantities up to two decimal places, and complete a POS transaction. Each transaction persists PHP amounts with 12% VAT added to VAT-exclusive prices, an optional free-text customer, payment details, checkout-time selling-price and COGS snapshots, and receipt details. Inventory is deducted through the stock ledger. Transactions are completed-only and immutable; voids, refunds, and accounting GL postings are outside this scope.

**Blocked by:** None (can start immediately).

**Status:** completed

- [x] Cash, card, and bank transfer payment methods are supported. Cash received must cover the total and change is calculated; card and transfer payments require exact payment and a reference number.
- [x] Checkout saves transaction and line details, including VAT, payment method, price and COGS snapshots, while deducting inventory; a failure leaves neither partial transaction records nor stock deductions.
- [x] Transaction history shows transaction number, date, customer, items, quantities, amount, VAT, payment method, and completed status.
- [x] The receipt shows the transaction details and PHP amounts.
- [x] Session-only POS demonstration checkout is removed; checkout does not create accounting GL postings.
