# Livewire Setup and Structure Proposal

**Status:** Approved design; the 19-screen authenticated module-page migration is complete. Accounting and tax production-release restrictions remain.

## Goal

Set up Livewire in the Laravel 12 project and reorganize the application using conventional Laravel structure. Migrate the existing application UI to a shared Blade/Tailwind design based on the existing green-and-gold company identity.

## Current project facts

- `composer.json` requires Laravel 12 and does not include Livewire.
- The dashboard is a large Blade file with inline CSS and JavaScript. Its inventory interactions call the existing `/inventory` CRUD endpoints; many sales, VAT, reporting, and accounting values are browser-side examples or placeholders.
- The login and registration pages use custom controllers. Registration creates `Employee` records; `config/auth.php` also configures the `employees` provider, so Employee is the existing authentication model.
- Inventory currently has `unit_cost` but no distinct selling-price field.

## Selected design

### Livewire and application organization

- Add Livewire to the existing Laravel application; keep the custom authentication flow rather than adopting a starter kit.
- Convert login and registration forms to Livewire while preserving Employee-backed authentication and registration behavior.
- Use named routes for individual application screens instead of keeping all sections under one dashboard URL.
- Use conventional Laravel locations for application code, Livewire components, routes, Blade layouts/views, and Vite-managed styles. Extract page-level CSS and JavaScript from large Blade files; use shared layouts and view partials where appropriate.
- Use Tailwind with Vite and unify the existing pages around the green-and-gold company identity.

### Application behavior

- Migrate the public homepage and the authenticated dashboard screens to the new Livewire/Blade structure.
- Persist inventory operations using the existing inventory domain, extending it with a selling price distinct from unit cost.
- Leave existing selling prices unset. Block checkout for any item without a selling price until a user enters one.
- Record paid POS sales with cash or card tender. Derive dashboard and report totals from persisted application records rather than browser-only fixtures.
- Provide VAT summaries only, not statutory returns or a claim of tax compliance. Use a clearly labeled, configurable indicative 12% VAT calculation for all sales; do not present the current UI's sample records as actual business activity.
- Persist the existing company settings as application data.
- Keep demo examples in an explicitly separate demo mode/environment with a visible indication; never mix demo records into production totals.

### Accounting boundary

Accounting behavior is deferred. Do not release the project as the requested full application until approved accounting rules are supplied and implemented. Needed inputs include the chart of accounts, sales/payables/payment posting rules, payable and disbursement lifecycles, and report definitions. Do not create functional accounting routes with invented rules or present placeholder data as real records.

## Proposed conventional locations

- `app/Livewire/`: page and interaction components, grouped by application area.
- `app/Models/`: persisted domain records.
- `app/Http/Controllers/`: retain only conventional controller responsibilities still needed after route migration.
- `routes/web.php`: named page routes and middleware.
- `resources/views/layouts/`: shared page layouts.
- `resources/views/livewire/`: Livewire component templates.
- `resources/views/components/`: reusable Blade presentation components.
- `resources/css/` and `resources/js/`: Vite-managed styling and JavaScript.

Exact component boundaries and model names should follow existing Laravel conventions during implementation rather than creating another abstraction layer.

## Approval and prerequisite

Production activation remains gated on approved accounts and mappings, cutover evidence, operational posting prerequisites and complete reporting rules. Accounting functionality described below is limited to the persisted records and supported coverage each workflow documents; it does not certify release readiness.

## Accounting implementation status

Production activation remains gated on approved accounts, mappings, cutover evidence and other prerequisites. Chart of Accounts records and approvals persist in the accounting tables; the page does not use illustrative ReferenceAccounts fixtures.

Journal Entry persists manual drafts, posted entries and linked corrections in the shared accounting journal tables. Drafts have no ledger effect; posted journals and lines are immutable and corrections preserve linked reversal/replacement history.

## Implemented authenticated shell and Settings slice

Migrated Livewire pages use `layouts.app` with the extracted shell assets in `resources/css/shell.css` and `resources/js/shell.js`. The authenticated shell links to the signed-in employee's Profile and to Dashboard, POS, Inventory, Tax Compliance, Accounting Overview, Chart of Accounts, Journal Entry, and Settings destinations.

Settings values are temporary session state, not business settings. The session key `demo.settings.employee.{employee-id}` scopes values to both the authenticated employee and their session; logout invalidates the session. Later demo-only state should use the same `demo.{area}.employee.{employee-id}` ownership and session-lifetime convention. Do not add storage APIs or placeholder features for those future areas.

## Employee management

Employee create and update forms use a native dialog controlled by the Livewire employee component. Role assignment remains permission-gated and uses Choices.js for searchable multiselect input; SweetAlert2 handles deletion confirmation and save/delete feedback. Both frontend dependencies are installed through npm and bundled by Vite.

## Activity log

The authenticated `/activity-log` Livewire page is available only to employees with the `activity-log.view` permission; its sidebar link uses the same permission. It shows all employees' successful persisted Inventory, Employee, and Role changes newest-first, with paginated actor/action/record details and before/after values for changed fields. Passwords and tokens are never recorded. Manual journal preparation, posting and correction have persisted actor/reason fields but are not included in this activity-log; session-only POS and Settings demonstrations and ordinary page views are excluded.

## Employee profile

The authenticated `/profile` Livewire page is available to every signed-in employee without an administrative permission. It updates only the current employee's username and password; password changes require the current password and keep the current session active. The page shows only audit events caused by that employee. Password changes add a safe event without recording password values.

## Dashboard

The authenticated `/dashboard` uses the `DashboardPage` Livewire component. Inventory count, value (quantity × unit cost), category totals, stock alerts, and recently added materials come from persisted inventory. Low-stock alerts include only items with positive quantity at or below reorder level; out-of-stock items have zero or negative quantity.

The dashboard shows the five newest inventory materials, ordered by creation time and then ID. Empty inventory and low-stock states are explicit. Demo sales, VAT and accounting totals, and payable or VAT-filing alerts are not shown on this dashboard.

The category chart renderer is loaded through the shared Vite bundle and initializes only when the dashboard root is present.

## Recorded POS VAT

The authenticated `/tax/vat-records` Livewire page requires `tax.view` and lists persisted VAT records linked one-to-one with completed POS transactions. Successful checkout saves the record in the same database transaction as sale lines and stock changes; the record copies the saved taxable subtotal, VAT rate, VAT amount, VAT-inclusive total, and UTC completion timestamp. The migration includes existing completed POS sales, and `php artisan tax:include-historical-pos-vat-records` can safely repeat inclusion without changing existing records.

Search matches transaction-number substrings; inclusive date filters and displayed completion dates use `Asia/Manila`, while timestamps remain stored in UTC. Details use the transaction's saved item-line snapshots. VAT records cannot be edited or deleted. The list contains POS activity only, not company-wide tax results.

## VAT summary and POS tax report

VAT Summary requires `tax.view` and aggregates saved `PosVatRecord` amounts by selected Manila calendar month or quarter. Its recorded-period service supports custom inclusive date ranges for the Tax Report; the preparation worksheet uses that same service’s calendar-quarter calculation. Empty periods show zero totals and an explicit no-recorded-sales state; figures identify POS-only scope, input VAT not captured, zero deductions applied, and a POS-only VAT payable estimate. VAT Summary does not establish the company’s final liability.

Tax Report requires `tax.view` and reports saved POS VAT for a Manila calendar month, calendar quarter or custom inclusive date range. It shows transaction details from saved sale-line snapshots, paginates the interactive list, exports every matching transaction to CSV, and prints all matching transactions from an authorized report surface. The report identifies Fabellion Construction and Development Corp., its POS-only scope, selected dates, reporting timezone and generation time. It is not a complete official return or submitted filing.

VAT Return Preparation requires `tax.view` and is a live, read-only worksheet over the same recorded-period VAT calculation as VAT Summary. It selects a Manila calendar year and quarter, displays saved POS totals and supporting transaction details, and exports a summary CSV or a browser-printable preparation copy. It is not an official Form 2550Q, a complete official return or a submitted filing; no approval, persisted snapshot or period lock is offered.

## Accounting overview

The authenticated `/accounting` route uses the Accounting-grouped `AccountingOverview` Livewire page and shared application shell. It selects the current Manila calendar month by default and applies the same inclusive date range to posted income flows, with balance positions through the selected end date. Revenue is VAT-exclusive; expenses include COGS and all expense classifications; Net Income is Revenue less Expenses. AP, GL-only AR, Cash & Bank (excluding card clearing), and Inventory are shown as period-end ledger positions. Unavailable cutover or account-classification coverage is labeled unavailable instead of zero. Recent posted journals link to their detail; drafts and opening cutover records are excluded. Quick actions navigate to Journal Entry, General Ledger, Trial Balance, and Financial Statements. This view does not represent production activation or statutory certification.

## Cash Disbursements

The authenticated `/accounting/cash-disbursements` Livewire page supports direct non-inventory disbursements and supplier settlements allocated to posted received-purchase invoices or active posted opening invoices. Supplier settlements require explicit positive allocations totaling the payment and limited to one supplier; posting debits Accounts Payable and credits the selected Cash/Bank account atomically. Returned or voided released checks and other payment corrections use linked reversals that preserve evidence and allocation history. Both payable registers derive status and balances from posted allocations net of reversals and print invoice payment allocation/correction history. Production release still requires approved accounts, mappings, and cutover controls.

## Financial Statements

The authenticated `/accounting/financial-statements` Livewire page generates an Income Statement from posted FCDC journal lines and approved statement classifications. It shows VAT-exclusive Sales, COGS, Gross Profit, operating expenses, other income/expenses and signed Net Income/Loss; opening balances, tax accounts, drafts, `fiscal_year_closing` journals and their linked corrections/reversals are excluded. It includes an approved pre-cutover YTD summary only for eligible full-year/YTD coverage and rejects unsupported earlier ranges.

Balance Sheet selection reports cumulative posted Asset, Liability and Equity balances as of an inclusive Manila date, including approved Cash/Bank, receivables, inventory, equipment, payables, ledger VAT, capital, retained earnings and separately calculated current unclosed earnings. It uses approved pre-cutover YTD earnings where required, excludes earnings already transferred through fiscal-year close, and shows an accounting error without an equity plug when the equation is unequal. Both statements show FCDC scope, generation time and limitations that they are not audited, filed or legally certified; unavailable or unapproved coverage is not shown as zero. Income Statement separately identifies a genuinely empty reporting period.

## Trial Balance

The authenticated `/accounting/trial-balance` Livewire page reads posted FCDC journal lines from the approved opening cutover through the inclusive Manila end date. It shows cumulative debit/credit closings, the prior opening balance and selected-period movement, including inactive accounts with posted balances. Centavo totals report unequal books without a balancing plug. The report separately compares Accounts Payable with posted supplier/invoice balances and Inventory with approved opening valuation plus valued stock movements at the same as-of date; unsupported inventory valuation is shown as an explicit coverage error. Dates before cutover and invalid ranges are unavailable rather than presented as zero.

## General Ledger

The authenticated `/accounting/general-ledger` page selects an approved account and inclusive Manila date range, reads posted FCDC journal lines, derives the opening balance from earlier posted activity, and displays deterministic running balances. Coverage starts at the approved opening-journal cutover; older arbitrary transaction-level ranges are unavailable. Journal detail links resolve through the Journal Entry page, and known operational source links target their existing detail pages only when the source record exists.

## Completed module-page cutover

The authenticated application has 20 individually named Livewire page routes under the shared `layouts.app` shell: Profile; Dashboard and Reports; Inventory Overview and Management; Point of Sale; VAT Records, VAT Summary, Tax Report, and VAT Return Preparation; Accounting Overview, Chart of Accounts, Journal Entry, Accounts Payable, Cash Disbursements, General Ledger, Trial Balance, and Financial Statements; Settings; and Activity Log. The public homepage, login, logout, and Inventory JSON endpoints retain their existing route contracts.

Navigation uses normal named-route links, route-derived active states, and route-derived parent dropdown expansion. There are no dashboard section switches or page mounts that include the other screens. The obsolete `resources/css/style.css` was removed after confirming it had no consumers; Vite's public and shared-shell entrypoints remain in use.

POS transactions and VAT records persist across sessions; approved POS accounting postings link completed sales and valued stock effects. Settings values remain session-scoped. Manual journal drafts, posted history and corrections persist in the accounting journal tables across logout. Inventory and POS behavior remains subject to approved operational and statutory rules; the presence of persisted activity does not establish production readiness.

Inventory and POS remain subject to approved operational and statutory rules. The General Ledger displays posted journal history within supported cutover coverage; Journal Entry and source workflows persist their documented records. Accounting Overview now summarizes posted period flows, end-date ledger positions, and recent posted journal detail when supported accounting cutover and classification coverage exists. Trial Balance and Financial Statements retain their documented calculation limits. VAT Return Preparation remains a live POS-only preparation aid, not an official return or filing. Do not interpret these implementations as approval to release production accounting or tax functionality; retain the prerequisites above.

Verification for this cutover: the authenticated browser smoke reached each of the 18 direct URLs and found the shared shell and one active navigation link on every page. Browser checks also confirmed Back/Forward, the active/expanded VAT Return Preparation navigation, mobile navigation without document overflow, and logout followed by guest redirection. `APP_ENV=testing SESSION_DRIVER=array DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test` passed all 80 tests (680 assertions). `npm run build` completed; Vite noted that `/image/construction-bg.jpg` remains a runtime URL, served from `public/image/`.
