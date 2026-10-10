# Accounting workflow design

Status: confirmed design and operating contract. Implementation is not authorized by this design-only approval.

## Confirmed foundations

- Target complete accrual books for one FCDC entity, not an operational subset presented as complete company financial statements.
- Include approved opening balances and authorized journals for activity outside operational modules; this does not authorize additional operational modules.
- Recognize assets, liabilities, income and expenses at the underlying economic event, separately from settlement. A credit equipment purchase debits Equipment and credits Accounts Payable; payment debits Accounts Payable and credits Cash/Bank.
- Require an authorized accounting reviewer's approval of the chart of accounts and posting-account mappings before production posting. Demonstration reference accounts do not satisfy this approval gate.
- Posted journal entries are immutable. Corrections use linked reversals and replacement entries, retaining the original and the correction reason. Drafts may be edited or deleted.
- Separate journal preparation and posting permissions. Authorized posters may post their own manual journals; mandatory maker-checker approval is not required.

## Confirmed source integration

- Begin with an accountant-selected cutover date and approved opening account balances supported by AP and stock schedules. Operational events from that date onward enter the new books. Do not repost pre-cutover activity already included in those balances.
- Complete supported POS sales, supplier invoices and cash disbursements with exactly one source-linked balanced accounting posting in the same transaction. Missing approved mappings or failed posting prevent source completion; no partial effects remain.
- Use perpetual moving weighted-average inventory cost, approved opening quantities and values, and valued receipts. Freeze each sale's accounting cost; later valuation changes must not rewrite posted COGS.
- Keep POS fully paid. Maintain non-POS Accounts Receivable and collections through authorized journals; the overview shows the GL balance, not customer-level aging or a collection subledger.
- Maintain applicable purchase/input VAT, adjustments and settlements through accountant-authorized journals. Preserve the separately approved POS-only tax-report scope and its estimate labeling; inventory cost does not establish VAT deductibility.

## Confirmed purchases and payments

- Post received supplier purchases as a combined invoice, valued receipt and balanced accounting event. Inventory lines debit Inventory; mixed non-inventory lines debit selected approved asset/expense accounts; the invoice total credits AP. Require confirmed receipt and prevent duplicate standalone receipts for the same purchase. Advance invoices remain drafts. Unmatched physical receipt/invoice timing is handled by authorized accrual journals rather than a new independent receiving/matching workflow.
- Allow partial payments and explicit allocations of one disbursement across multiple invoices of the same supplier. Reject allocations above outstanding amounts and do not support supplier advances within AP settlement. Derive unpaid, partially paid and paid states from posted balances; derive overdue from due date and outstanding balance.
- Cash Disbursements supports AP settlements and multi-account direct payments. AP settlements debit AP; direct purchases/expenses debit their actual approved accounts; credit the selected Cash/Bank account. Direct inventory purchases require linked valued stock receipts.
- Post checks on release to the payee, not preparation or bank clearance. Require check number and payment/release date; unreleased checks remain drafts. A returned or voided released check requires a linked reversal. This does not add bank-clearing reconciliation.
- Map POS cash receipts to Cash, confirmed bank transfers to the selected Bank account, and card sales to Card Settlement Receivable/Clearing at gross value. Authorized journals record later card settlement and fees; no processor integration is included.

## Confirmed inventory and period controls

- Recovered materials require an accountant-approved recovery cost basis, value and offsetting account under the applicable recovery/project policy before acceptance. Debit Inventory and credit that approved counterpart; never infer recovery income or cost from selling price, and do not create AP.
- Map non-sale issues and decreases to approved project-use, loss or adjustment accounts at the current moving-average cost. Increases require approved value and credit counterpart. Reason and accounting authorization are required; quantity, carrying value and GL effects complete atomically.
- Maintain forward-only valuation chronology per item. Reject effective valuation timestamps preceding that item's latest valued movement; order same-date movements deterministically. Corrections and reversals create new current-period linked movements, never rewrite past sale costs. Non-inventory journals may use otherwise permitted open-period dates.
- Initially record purchases against their gross debit allocation. Later allowable-input-VAT recognition uses source-linked, accountant-authorized reclassification from the original asset/expense allocation, never a second reduction of AP. Inventory corrections adjust the valued stock schedule and allocate the change between remaining Inventory and consumed cost; original sale postings remain immutable. Tax pages remain POS-only.
- Use calendar-month accounting periods in Asia/Manila. Closing blocks every source, journal and reversal posting into that period. Normal corrections enter the current open period. An authorized accounting administrator may reopen a month with a recorded reason; reopening does not waive inventory's forward-only chronology.

## Confirmed account and report structure

- Keep five account types: Asset, Liability, Equity, Revenue and Expense. Require approved statement classifications and normal debit/credit behavior, including approved contra accounts. COGS is an Expense with its own statement classification, not a sixth account type.
- Only active posting accounts accept new entries. Used accounts cannot be deleted or silently retyped/reclassified; deactivation preserves history.
- Use a corporate equity model with accountant-approved capital accounts, retained/accumulated earnings and separately derived unclosed current-period earnings. The final chart must verify suitability against FCDC's actual records; the company name alone does not prove legal form. Do not count earnings twice after closing.
- Generate Trial Balance as cumulative closing account balances through the selected end date, including opening balances and preceding postings. Supporting columns show opening balances and selected-period debit/credit movements. Place each closing balance in its actual debit or credit column. Unequal closing totals are an error, never corrected with an automatic plug.
- Use a January-December fiscal year. Income Statement uses an inclusive date range; Balance Sheet uses a single as-of date. Separate year-end closing effects from operating activity so annual closing does not erase reported income.
- Maintain stable supplier identities and searchable names; preserve supplier/invoice details on posted records. Reject duplicate normalized invoice numbers for the same supplier across active posted invoices; different suppliers may use the same number. Reversals preserve linked history.

## Confirmed reconciliation and correction rules

- Overview Revenue is VAT-exclusive total revenue for the selected period. Expenses includes COGS and other expenses; Net Income equals Revenue minus Expenses. AP outstanding, GL-only AR, valued Inventory and explicitly labeled Cash & Bank are positions as of period end. Cash & Bank excludes card clearing. Recent journals are posted entries only.
- Income Statement separates Sales, COGS, Gross Profit, operating expenses and other income/expenses rather than omitting non-sales income or non-operating expenses.
- Use dependency-aware source-linked correction chains. Reversing a payment restores its original invoice allocations. A financial purchase correction does not reverse a real physical receipt unless an explicit authorized physical correction also occurs. Correct supplier obligations and remaining/consumed costs through linked entries; never cascade-delete payments or stock history.
- Treat AP and Inventory as controlled accounts. Ordinary bare journals cannot bypass their supporting schedules. Controlled openings, source corrections and valuation/VAT adjustments update schedules and GL atomically. Opening AP includes supplier/invoice balances; opening Inventory includes item quantities and values. GL-only AR remains available through ordinary authorized journals.
- For source-linked purchase VAT reclassification, require accountant-approved amounts and supporting rationale. Input VAT debit equals credits to remaining Inventory, attributable consumed cost and other original purchase accounts. Validate source purchase value, previously reclassified amounts and remaining carrying value. Update stock carrying value and GL together; do not infer an automatic tax-cost split or rewrite original COGS.
- Require balanced books and matching AP/Inventory schedules before monthly or annual close. An explicit accountant-authorized, exactly-once fiscal-year close transfers revenue/expense balances into approved retained earnings. Income reporting excludes closing entries; Trial Balance and Balance Sheet include them. Reopening a fiscal year reverses its linked closing entry before corrections and requires a new close after reconciliation; current earnings must not be counted twice.

## Confirmed historical coverage and dependent periods

- Allow midyear cutover with separately approved pre-cutover year-to-date income/expense opening schedules. Full-year/YTD statements identify those opening summary amounts separately. Transaction-level reports begin at cutover; arbitrary earlier date ranges are unavailable without supporting historical data. Never invent historical transactions.
- A paid invoice corrected below its settled amount creates a source-linked Supplier Refund Receivable for the excess, not negative outstanding AP or an intentional supplier advance. Clear an actual refund through a linked authorized receipt journal; do not silently apply it to another invoice.
- Reopening an earlier period requires explicit authorization for all later closed dependent periods. Reverse affected annual closing entries, correct and reconcile the books, then reclose chronologically. Record the reason and preserve closing/reversal history; do not silently change later reports while leaving their periods marked final.

## Confirmed operating contract

### 5.1 Accounting Overview

- Use one selected period consistently for income flows and its end date for balance cards. Initially select the current Manila month.
- Display Revenue, Expenses, Net Income/Loss, AP outstanding, GL-only AR, Cash & Bank and Inventory using the confirmed definitions.
- Show recent posted journals with date, reference, source and description; exclude drafts and demonstration history.
- Quick actions lead to New Journal Entry, General Ledger, Trial Balance and Financial Statements.
- Before accounting activation, show an explicit unavailable/unapproved state rather than zero or fabricated balances. After activation, genuinely empty periods show zero activity with a no-posted-activity explanation.

### 5.2 Chart of Accounts

- Require unique account code, account name, one of the five account types, approved statement classification, normal balance and active/inactive status; retain description.
- Account codes identify accounts, not report formulas. Classifications distinguish Cash/Bank, AR, Inventory, Equipment, AP, applicable VAT, corporate capital/retained earnings, Sales, COGS, operating expenses and other approved income/expense groups.
- Code/type/statement classification of a used account must remain historically stable. Do not delete used accounts. Name/description changes must preserve posted historical identification.
- New accounts and changed posting mappings require authorized accounting approval before operational use. Inactive accounts preserve their historical balances but cannot be selected for new posting.
- Demonstration accounts and journals are never migrated as business records.

### 5.3 Journal Entry

- Persist drafts and posted entries separately from demonstration/session state. Support create, edit draft, delete draft, validate, post, detail, search and filters.
- Require accounting date, unique journal identifier/reference, source, description and account lines. Retain external/source references separately where applicable; corrections receive distinct identifiers and links to their originals.
- Each posted line has exactly one positive debit or credit, in PHP centavos; require at least two valid lines, a positive total and exact equality of total debits and credits. No floating-point tolerance, automatic balancing plug or empty posting.
- Manual entries identify their source as manual; users cannot impersonate an operational source or create duplicate source postings. Posting checks permission, approved accounts/mappings, cutover coverage and open period on the server.
- Completed economic events cannot be future-dated. Drafts may be prepared ahead of time. Use Manila business dates without changing established UTC timestamp storage.
- AP/Inventory journal lines require the confirmed controlled, schedule-linked operation. Reversals and replacement entries retain reason, actor and source links; recheck posting eligibility rather than bypassing inactive-account or closed-period controls.

### 5.4 Accounts Payable

- Maintain supplier identity, supplier invoice number, purchase/recognition date, due date, gross amount, description, terms and allocated purchase lines. Due date is authoritative; descriptive terms do not silently change it.
- Draft invoices are editable/deletable and excluded from balances. Posting confirms the purchase/receipt and creates the required invoice, valued receipt and journal together; enforce supplier/invoice duplicate control.
- Totals show posted purchases, net valid allocated payments, outstanding and overdue. A partially paid overdue invoice remains overdue while its outstanding amount is positive. Payment reversal restores outstanding amounts.
- Derive status from posted source/correction/payment records; users cannot mark an unpaid invoice paid by editing status. Supplier refund receivables remain separate from outstanding AP.
- Support search/filter by supplier, invoice/reference, dates and derived state; view/print the invoice, allocations, corrections and balances. Printed records distinguish drafts from posted records.

### 5.5 Cash Disbursements

- Capture payee/supplier, payment/release date, method, amount, selected actual Cash/Bank account, reference, description and supporting document or reference.
- Methods are Cash, Bank, Check and another explicitly approved method. Method labels do not substitute for an approved money-account mapping. Require bank/payment reference for non-cash methods and check number for checks.
- An AP disbursement allocates its amount exactly across eligible outstanding invoices of one supplier; do not allow negative allocations, excess settlement or unrelated suppliers.
- A direct disbursement allocates its debit amount to approved accounts and, for inventory purchases, linked valued receipts. Debit totals equal the credited payment amount.
- Drafts have no AP/cash/stock/GL effects. Posting recognizes a completed payment and is atomic; released-check reversal and other payment corrections preserve source and allocation history.

### 5.6 General Ledger

- Read posted journal lines only, including approved openings, operational source postings, stock/valuation corrections, reversals and fiscal closing entries. No separate manual GL encoding.
- Filter by account and inclusive Manila date range; show date, journal/source reference, description, debit, credit and running balance.
- Compute the opening balance from all earlier posted activity, then apply in-range lines in deterministic accounting-date/posting order. Show the balance's debit/credit side explicitly, including unusual or contra balances.
- Retain links from GL lines to POS, supplier invoice, disbursement, stock valuation or manual journal sources.

### 5.7 Trial Balance

- Use the confirmed cumulative as-of balances, with opening and period-movement support. Include inactive accounts with relevant balances/history rather than dropping them.
- Compare exact closing debit/credit totals. Any inequality shows an accounting-error state; never fill a balancing difference automatically.
- Reconciliation also compares AP to its supplier/invoice schedule and Inventory to its valued-stock schedule. A balanced Trial Balance alone does not prove complete or correct source recognition.

### 5.8 Financial Statements

- Income Statement: VAT-exclusive Sales minus COGS equals Gross Profit; subtract operating expenses, then include separately classified other income/expenses to arrive at Net Income/Loss. Display loss with its correct sign.
- Exclude fiscal closing entries and their reversals from operating income reporting. Exclude ordinary opening balances from selected-period activity; include approved pre-cutover YTD nominal-account summaries only when generating an eligible YTD/full-year report, with explicit coverage labeling.
- Balance Sheet: classify posted as-of balances into approved Assets, Liabilities and Equity, including Cash, Bank, AR, valued Inventory, Equipment, AP, applicable VAT, corporate capital, retained/accumulated earnings and unclosed earnings.
- Do not substitute the POS-only VAT estimate for a company ledger liability. Input VAT and output VAT follow approved ledger classifications and accountant-entered adjustments; no tax deductibility or registration is inferred.
- Assets must equal Liabilities plus Equity exactly in centavos. Inequality shows an accounting-error state and blocks period closing, not a fabricated equity adjustment.
- Reports identify scope, dates, generation time and historical-summary limitations. They are not represented as audited, filed or legally certified statements.

### Activation, authorization and valuation invariants

- Production activation requires actual accountant-approved accounts/mappings, cutover date, balanced opening journal, matching supplier/item schedules, approved recovery/adjustment policies and any required pre-cutover YTD summaries. These approvals and business values are deployment prerequisites, not invented by this design.
- If cutover precedes activation, reconcile intervening existing source events exactly once using approved valuation evidence; creating their accounting links must not create duplicate sales, payments or physical stock movements. Missing historical costs block activation, not a guessed valuation.
- Keep quantities and carrying values as recorded precision. Compute issue value from the remaining moving-average quantity/value and round the posted movement to centavos; full depletion consumes the entire remaining carrying value so rounding cannot strand residual value.
- Valuation adjustments do not change quantity unless an explicit physical correction requires it. Reject a proposed correction whose quantity/value is unsupported, negative or exceeds its permitted source/remaining amount; preserve original valuation history.
- Separate view, prepare, post, account/mapping approval, valuation authorization, close and reopen capabilities. Automatic accounting effects follow the authorized source action; a cashier does not need accounting-page access merely to complete an approved checkout.
- Revalidate permissions and financial invariants at execution, not only in UI controls. Persist actor, source, posting time, correction links and approval/close/reopen reasons; do not treat ordinary viewing as a financial posting.
- No new customer-AR module, supplier-advance application, separate receiving/matching module, bank-reconciliation module, POS refund/cancellation workflow, payment-processor integration or expanded tax-report scope is authorized.

## Acceptance scenarios

1. Reject an unbalanced manual journal; a balanced authorized posting affects all downstream reports once, while saving/editing/deleting a draft affects none.
2. Reject missing approved mappings or a closed-period posting on every source path; a failed source/posting operation leaves no completed source or partial AP/cash/stock/GL changes.
3. Post a received credit equipment purchase of PHP 50,000: debit Equipment and credit AP. Paying it debits AP and credits Cash/Bank, with no second equipment/expense recognition.
4. Allocate PHP 35,000 across same-supplier invoices of PHP 30,000 and PHP 20,000: settle the first and leave PHP 15,000 on the second. Reject excess allocation and restore balances when reversing that payment.
5. Reject a repeated active invoice number for the same supplier; accept that number for a different supplier. Corrections preserve the original linked records.
6. With 10 units valued at PHP 100 each, receive 10 at PHP 200 each: remaining inventory is 20 units/PHP 3,000. Sell 2: record PHP 300 COGS and retain 18 units/PHP 2,700. Do not use an independently edited item cost.
7. Complete a PHP 180 VAT-exclusive cash sale at 12%: debit Cash PHP 201.60, credit Sales PHP 180 and Output VAT PHP 21.60, plus the separate frozen COGS/Inventory effect. A card sale debits card clearing, not Bank.
8. Accept recovered stock only with its approved value/counterpart; non-sale stock loss debits its approved loss account rather than POS COGS. Reject a valuation event preceding the item's latest valued event.
9. Reclassify allowable purchase VAT with reviewed remaining/consumed-cost amounts: preserve AP, original sale postings and physical quantity while updating carrying value and the linked accounts together.
10. Correct a PHP 50,000 fully paid invoice to PHP 40,000: AP outstanding remains zero and PHP 10,000 becomes linked Supplier Refund Receivable. A linked receipt journal clears the actual refund.
11. An account with no selected-month activity retains its opening balance in GL/Trial Balance. Include the full Manila end date and distinguish period flows from end-date positions.
12. Include approved pre-cutover YTD summary values only in eligible YTD/year statements, label them, and reject unsupported arbitrary pre-cutover periods.
13. Close the fiscal year once: retained earnings changes, unclosed earnings is not duplicated, and the year's Income Statement remains intact. Reject duplicate closing and hidden changes to dependent closed periods.
14. Fail AP/stock reconciliation or the accounting equation: show a financial-error state and block closing without a balancing plug.

## Final confirmation

The user confirmed the complete design, operating contract and shared understanding, with design-only approval. Three focused ADRs record the consequential boundary, posting and valuation trade-offs. No accounting implementation is authorized by this confirmation.

- [ADR 0004: Approved accrual accounting cutover](adr/0004-approved-accrual-accounting-cutover.md)
- [ADR 0005: Atomic immutable source-linked postings](adr/0005-atomic-immutable-accounting-postings.md)
- [ADR 0006: Forward-only moving-average valuation](adr/0006-forward-only-moving-average-valuation.md)

## Repository evidence

- Accounting components and `app/Support/ReferenceAccounts.php` currently provide demonstration behavior, not approved production books.
- `app/Livewire/Pos/PointOfSale.php` persists completed cash, card and bank-transfer sales, saved sale/VAT amounts, line unit-cost snapshots and stock depletion. It does not currently post accounting entries.
- `app/Models/StockMovement.php` records immutable quantity movements and corrections; its movement fields do not establish historical inventory valuation.
- `docs/vat-compliance-design.md` confirms a POS-only tax scope and explicitly excludes purchase-based and accountant-entered input VAT. Extending accounting treatment does not implicitly change that tax scope.
- Some earlier current-state documentation describes POS as session-only. Current persisted checkout code is the source evidence for this interview; unrelated documentation is not revised here.

At initial design approval, no accounting implementation or runtime verification was claimed.

## Implemented chart and mapping maintenance

The chart-of-accounts issue adds persisted production account and source-account mapping records without seeding the demonstration reference accounts or journal history. Account maintenance (`accounting.maintain-accounts`) and approval (`accounting.approve-accounts`) are separate from viewing. New or changed accounts require approval; changed source mappings return to pending approval. These approval resets and used-account identity restrictions are enforced by the persisted models as well as the Livewire actions. Only active approved accounts and mappings with a source-compatible statement classification pass the posting-eligibility checks. Used accounts retain their records when deactivated, cannot be deleted, and cannot change code, type or statement classification.

The chart/mapping increment did not implement journals or connect operational posting paths. Opening-books later establishes persisted rules for opening balances and YTD summaries. Supplier credit purchases and valued non-sale stock movements now post source-linked entries; POS, supplier payments, recoveries and other operational sources remain separate integrations.

## Implemented cutover and opening books

`accounting.opening-books` provides separate `accounting.maintain-opening-books` and `accounting.approve-opening-books` actions. It persists the Manila accounting date, January–December posting period, source identity, prepared/posted actors, UTC approval/posting timestamps, approved opening lines and separately approved pre-cutover YTD income/expense schedules. Opening balances use exact PHP centavos and approved active accounts. Posted journals and lines, plus approved YTD summaries and lines, cannot be edited or deleted through their models.

The supplier-payables increment adds stable supplier identities, actor-logged supplier maintenance, editable opening-invoice drafts with historical supplier-detail snapshots, normalized supplier-scoped invoice uniqueness at posting, and immutable posted invoice history. Multiple matching draft numbers may be prepared, but approval rejects duplicate normalized numbers among records that would post. `accounting.maintain-suppliers` gates supplier maintenance; `accounting.maintain-opening-books` gates opening-invoice preparation and `accounting.approve-opening-books` gates approval. Opening invoice approval is atomic with the opening journal: the schedule total must equal the controlled Accounts Payable credit lines, and each posted schedule record is linked to that journal with preparer, poster, approver and timestamps. Drafts create no purchase, expense, receipt or journal effects. Posted opening balances, due-date-based overdue figures and invoice detail/print for drafts and posted records appear in Accounts Payable. Supplier schedule readiness reports the actual reconciled or mismatched state; Inventory and valuation-policy readiness still block production activation. This does not enable operational purchase posting, general-ledger reports or production activation.

## Implemented persistent manual journals

`accounting.journal-entry` now persists manual drafts and posted journals in the shared accounting journal tables. `accounting.create-journal-entry` gates preparation; `accounting.post-journal-entry` separately gates posting and correction, and an authorized poster may post their own draft. Manual source identity is assigned server-side. Drafts remain editable/deletable and outside posted balances. Posting rechecks exact PHP-centavo balance, active approved accounts, cutover coverage, nonfuture Manila date and open calendar-year period. Bare Accounts Payable and Inventory entries are rejected; GL-only receivable and card-clearing accounts remain available.

Posted manual entries and lines are immutable. Corrections atomically post a distinct linked reversal and replacement with correction reason, actor and references, dated in the current Manila day. The journal register supports reference/description/external-reference search, inclusive accounting-date bounds, source filtering and entry detail. This does not add operational source posting, customer receivables, card processor or settlement workflows, or production activation.

`accounting.general-ledger` reads only posted FCDC journal lines and approved accounts. It derives an opening balance from posted activity before the selected Manila range, then applies lines by accounting date, journal ID and line ID to show actual debit/credit running balances, including inactive accounts with posted history and periods with no activity. The report rejects dates before the posted opening-balance cutover, includes the full selected end date, and links each journal reference to posted journal detail. Existing supplier-invoice, disbursement, completed POS, and stock-movement source details are linked only when their source record exists; the ledger remains a view over the shared journal tables.

`RecordValuedStockMovement` posts non-sale receipts, issues and counted adjustments in one database transaction with immutable stock history, a balanced source-linked journal, exact carrying-value cents and the updated item quantity. `inventory.movements.record` authorizes movements; positive value changes also require `inventory.valuation.approve`. Posting requires approved opening valuation, active approved Inventory/counterpart mappings, a source reference, forward valuation chronology, a nonfuture date and an open period. Quantity-only receipt/issue/adjustment/recovery/reversal services reject dates on or after cutover. The opening approval seeds current item carrying value; inventory views and reports use that balance rather than a separately editable item cost. This path does not create Sales, AP or POS COGS entries.

Recovered-material acceptance uses the approved `recovery_offset` mapping as the accountant-approved recovery/project cost policy and requires `inventory.valuation.approve` for the explicitly assigned positive unit value. `AssessRecoveredMaterial` posts only the accepted portion through `RecordValuedStockMovement`; assessment, valued receipt, inventory quantity/carrying value and the Inventory-debit/counterpart-credit journal share one transaction. The immutable assessment records assigned unit/total value, approver and counterpart; project/recovery IDs remain on the stock movement and the recovery history shows the linked journal. Rejection requires a reason and never changes stock. No purchase payable, Sales Revenue, selling-price inference or zero-value fallback is created.

## Implemented supplier payable settlements

Cash Disbursements supports supplier settlements alongside existing direct payments. A settlement draft stores one supplier and explicit positive allocations to that supplier’s posted received-purchase invoices or active posted opening invoices; posting locks the referenced invoices, rechecks their net outstanding balances, and atomically creates the AP-debit/Cash-or-Bank-credit journal. It never debits purchase or expense accounts again. Drafts do not affect payable balances.

Posted allocation rows remain linked to the immutable payment record. Paid, partially paid, unpaid, outstanding, and overdue amounts derive from posted allocations net of linked reversal rows. Payment corrections preserve the original payment and evidence, copy the original invoice allocations onto the linked reversal, and restore invoice balances without deleting history. The supplier-purchase and Accounts Payable registers show paid/outstanding/overdue totals and printable invoice allocation/correction history; the disbursement register searches payment references, evidence, supplier, and both invoice types.

Invoice rows are locked in stable ID order during posting so competing drafts cannot oversettle an invoice. Allocation, journal and payment writes share one database transaction; failed posting leaves the draft, AP schedule and cash/bank journal effects unchanged. Settlements now cover posted received-purchase and active posted opening supplier invoices.

## Implemented financial statements

The Financial Statements page calculates an inclusive Manila-period Income Statement from posted FCDC journal lines and currently approved statement classifications. Sales, COGS, operating expenses, other income and other expenses remain separate; ordinary opening entries, tax accounts, drafts, fiscal-year-closing journals (`source_type=fiscal_year_closing`) and all linked corrections/reversals are excluded. Revenue and expenses use their actual debit/credit direction, with centavo-exact Gross Profit and Net Income/Loss.

When an Income Statement period begins before cutover, the report accepts only the current fiscal year's January 1-to-YTD/year range backed by the approved pre-cutover summary through the day before cutover. It labels that historical summary and distinguishes missing cutover/YTD approval or unsupported coverage from a genuinely empty period.

Balance Sheet selection reports cumulative posted FCDC Asset, Liability and Equity balances as of the selected Manila date, using approved classifications, including contra balances and approved pre-cutover YTD earnings where required. It derives current unclosed earnings without duplicating posted fiscal-year closing transfers, includes the ledger's Input and Output VAT balances (not POS-only VAT estimates), and reports an exact centavo accounting-equation error without an equity plug. It displays the selected date, scope, generation time and certification limitations. Both statements remain protected by `accounting.view`; neither claims audit, filing or legal certification.
## Implemented supplier purchase corrections and refunds

Posted supplier purchases remain immutable. Accountants with `accounting.correct-supplier-purchases` post a reasoned, source-linked correction with reviewed line allocations, a distinct active invoice identity for credit purchases, and retained payment/receipt histories. Direct-paid purchases can be corrected downward against an identified supplier, including subsequent downward corrections. Inventory corrections preserve received quantity and split value changes between remaining stock and consumed expense; forward chronology, approved accounts, open periods, and valuation authorization are enforced atomically.

Supplier refunds are recorded only when received, by an authorized user selecting an active approved Cash/Bank account and linking the receipt journal to the correction. Purchase, disbursement, and print details show correction and refund history; accounts payable and trial balance schedules account for credit-purchase correction postings without duplicating replacement invoices.
