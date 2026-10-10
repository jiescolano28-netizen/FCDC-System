# Construction Inventory and Sales

Business terminology for the construction-material inventory application. Illustrative activity is distinct from recorded business activity.

## Language

**Access permission**:
A capability granted through an employee's assigned role that determines which business areas or actions the employee may use.


**Audit activity**:
A record of a successful change to persisted business or access-control data, identifying the actor, action, affected record, and changed fields. Illustrative and session-only interactions are not audit activity.
_Avoid_: Demo activity when referring to an audit record

**Role**:
A named set of access permissions that an employee can hold.

**Employee role assignment**:
The association of one or more roles with an employee; an employee may hold multiple roles at the same time.

**Inventory item**:
A construction material tracked by name, category, unit, quantity, cost, and stock-reorder threshold.
_Avoid_: Product when referring to the tracked inventory record

**Stock transaction**:
A record of a stock receipt, issue, or counted-balance adjustment for an inventory item.

**Stock adjustment**:
A correction that sets an inventory item's on-hand balance to its physically counted quantity.

**Demolition project**:
A source project record used to identify where recovered materials came from.

**Recovered material**:
Material recovered from a demolition project and assessed for acceptance into inventory.

**Recovered-material assessment**:
An immutable decision recording the accepted and rejected portions of a recovered material, its inventory item, and any posted stock receipt. A correction reverses the earlier receipt and adds a linked reassessment.

**POS transaction**:
A completed sale of inventory recorded with its customer, payment, taxes, and item details.

**POS transaction line**:
One inventory item and quantity included in a POS transaction, with the selling price and unit cost fixed for that sale.

**VAT**:
The tax amount calculated on the VAT-exclusive prices used for a POS transaction. A displayed rate alone does not establish statutory compliance.

**Standard-rated POS sale**:
A POS sale subject to 12% VAT on its VAT-exclusive selling amount. VAT-exempt and zero-rated sales are distinct tax treatments.

**Taxable sales**:
The VAT-exclusive selling amounts of standard-rated sales.
_Avoid_: VAT-inclusive total when referring to taxable sales

**Output VAT**:
VAT charged on taxable sales, distinct from VAT incurred on purchases.

**VAT record**:
An immutable tax record linked to one completed POS transaction, carrying that sale's taxable amount, output VAT and VAT-inclusive total.
_Avoid_: VAT demonstration record when referring to recorded business activity

**Allowable input VAT**:
VAT incurred on purchases that qualifies for deduction from output VAT under the adopted tax treatment. Inventory cost alone does not establish an allowable deduction.

**POS-only VAT payable estimate**:
Recorded output VAT from POS sales less the allowable input VAT deductions applied within the report's scope. It is not necessarily the company's final VAT liability.
_Avoid_: Final company VAT payable

**VAT reporting period**:
A calendar month, calendar quarter or inclusive date range determined by completed-sale dates in Philippine local time.

**POS-sourced VAT report**:
A report of VAT figures from recorded POS sales, not necessarily the company's complete taxable activity.
_Avoid_: Company-wide VAT report when only POS sales are included

**VAT preparation worksheet**:
A quarterly summary of sales and VAT figures prepared for review before return preparation. It is neither an official Form 2550Q nor a submitted return.
_Avoid_: Filed return, official 2550Q


**VAT demonstration record**:

An illustrative VAT-related transaction used to demonstrate records, summaries, and internal reports. It is not evidence of actual taxable activity or an official tax return.
_Avoid_: Actual VAT transaction, compliant tax record

## Accounting language

**Reference account**:
An illustrative account fixture used by Accounting demonstration pages. It is not an approved production account or a persisted business record.
_Avoid_: Approved account, production chart of accounts

**Approved chart of accounts**:
The set of FCDC accounting accounts reviewed and approved by an authorized accounting reviewer for use in its books.
_Avoid_: Reference accounts when referring to the approved chart

**Accrual accounting**:
FCDC's accounting basis in which assets, liabilities, income and expenses are recognized when the underlying economic event occurs, separately from cash receipt or payment.

**Journal entry**:
A dated accounting record allocating an economic event to accounts through debit and credit lines. A draft is not part of the posted books.
_Avoid_: Stock transaction, POS transaction when referring to the accounting record

**Posted journal entry**:
A balanced journal entry admitted to FCDC's accounting books that cannot be edited or deleted.
_Avoid_: Saved demonstration entry

**Journal reversal**:
A linked accounting entry that offsets an earlier posted entry while preserving that original record. A correction uses a reversal and a replacement entry rather than rewriting posted history.

**Accounting cutover date**:
The approved date from which FCDC's new accounting books recognize operational activity, supported by opening balances representing the preceding position.

**Opening accounting balance**:
An approved account balance carried into the books at cutover, supported by the relevant underlying schedules.
_Avoid_: Historical source posting when referring to a carried-in balance

**Moving weighted-average inventory cost**:
The inventory valuation basis that combines the remaining quantity and carrying value with each valued receipt to determine the cost of subsequent issues.

**GL-only receivable**:
A customer amount owed to FCDC that is recorded through accounting journals without a customer-invoice or aging subledger in this system.

**Supplier invoice**:
A supplier's claim for a recorded purchase, allocated to the inventory, asset or expense acquired. A posted credit-purchase invoice creates an amount owed to that supplier.

**Opening supplier invoice**:
An unpaid supplier invoice carried into the accounting cutover schedule and posted with the controlled opening Accounts Payable balance, without posting its purchase, expense or stock receipt a second time.

**AP settlement**:
A posted payment allocated to one or more outstanding invoices of the same supplier, reducing the amounts owed without recognizing the purchase again.
_Avoid_: Purchase expense when referring to payment of an existing payable

**Direct disbursement**:
A payment recorded against its actual acquired asset, expense or other approved debit account without settling an existing AP invoice.

**Released check**:
A check delivered to its payee and recognized as a book payment, whether or not the bank has cleared it.
_Avoid_: Prepared check when referring to a posted payment

**Card settlement receivable**:
The gross amount of a completed card sale awaiting settlement to FCDC's bank account, distinct from cash or bank funds already received.

**Valued stock movement**:
A receipt, issue or adjustment carrying both an inventory quantity change and its approved accounting value. It differs from a quantity-only stock record.

**Recovery valuation**:
The accountant-approved cost assigned to accepted recovered material, together with the accounting counterpart that explains that inventory value.
_Avoid_: Selling price when referring to recovered-material cost

**Purchase VAT reclassification**:
An authorized accounting correction transferring allowable purchase VAT from the original purchase cost to input VAT without changing the supplier obligation again.

**Closed accounting period**:
A calendar month whose books prohibit further posting unless an authorized accounting administrator explicitly reopens it.

**Account type**:
An account's fundamental Asset, Liability, Equity, Revenue or Expense classification.
_Avoid_: Statement classification when referring to the account type

**Statement classification**:
An approved grouping determining an account's placement in FCDC's financial statements; COGS is a statement classification within the Expense account type.

**As-of trial balance**:
A list of cumulative closing account balances through a selected date, separated into debit and credit balances.
_Avoid_: Period movement report when referring to a trial balance

**Unclosed earnings**:
Income less expenses not yet transferred into retained/accumulated earnings by a fiscal-year closing entry.

**Control account**:
An accounting account whose balance must agree with an identified supporting schedule. FCDC's Accounts Payable and Inventory require linked changes to their supplier or valued-stock schedules rather than unrestricted journals.

**Fiscal-year closing entry**:
An authorized accounting entry transferring that year's revenue and expense balances into retained earnings without removing the year's operating results from income reports.

**Cash and Bank balance**:
Funds recorded in approved cash and bank accounts, excluding unsettled card proceeds.

**Pre-cutover YTD opening schedule**:
An approved summary of the current fiscal year's income and expenses before accounting cutover. It supports explicitly labeled year-to-date or annual results without claiming transaction-level historical coverage.

**Supplier refund receivable**:
An amount owed back to FCDC after an authorized correction reduces an already-paid supplier purchase. It is distinct from outstanding AP and an intentional supplier advance.

**Received supplier credit purchase**:
A posted supplier invoice whose approved allocations recognize Accounts Payable and the acquired inventory, asset or expense in one atomic source-linked journal; inventory allocations also record valued stock receipts.

**Purchase allocation**:
The portion of a supplier invoice assigned to an inventory item and receipt quantity or to an approved non-inventory asset or expense account.
