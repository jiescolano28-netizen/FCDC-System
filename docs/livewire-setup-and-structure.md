# Livewire Setup and Structure Proposal

**Status:** Approved design; implementation in progress.

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

The design is approved. Implementation may proceed for the approved demonstration scope, but the application must not be released as the requested full application until the accounting rules are supplied and implemented. Production accounting remains deferred pending an approved chart, sales/payables/payment posting rules, payable and disbursement lifecycles, and report definitions.

## Accounting demonstration pages

Production accounting remains deferred: no posting rules, durable account records, payables/disbursements, ledger calculations, or real financial reports are implemented. The authenticated Chart of Accounts page uses `ReferenceAccounts::all()` as illustrative fixtures for chart filters and journal account selection; those fixtures are neither approved production accounts nor persisted records.

The authenticated Journal Entry page keeps its draft and balanced demonstration history under `demo.journals.employee.{employee-id}` in the signed-in session. Saving reports that the entry is not posted; the session history is not included in Accounting Overview balances or postings. Logout invalidates this temporary state with the rest of the session.

## Implemented authenticated shell and Settings slice

Migrated Livewire pages use `layouts.app` with the extracted shell assets in `resources/css/shell.css` and `resources/js/shell.js`. The authenticated shell links to Dashboard, POS, Inventory, Tax Compliance, Accounting Overview, Chart of Accounts, Journal Entry, and Settings destinations.

Settings values are temporary session state, not business settings. The session key `demo.settings.employee.{employee-id}` scopes values to both the authenticated employee and their session; logout invalidates the session. Later demo-only state should use the same `demo.{area}.employee.{employee-id}` ownership and session-lifetime convention. Do not add storage APIs or placeholder features for those future areas.

## Employee management

Employee create and update forms use a native dialog controlled by the Livewire employee component. Role assignment remains permission-gated and uses Choices.js for searchable multiselect input; SweetAlert2 handles deletion confirmation and save/delete feedback. Both frontend dependencies are installed through npm and bundled by Vite.

## Dashboard

The authenticated `/dashboard` named route uses the `DashboardPage` Livewire component in `app/Livewire/Dashboard/`. Inventory count, value, category totals, and reorder alerts are calculated from persisted inventory. Demo sales remain employee-session data and are shown as demonstrations, separate from the chart's illustrative fixed series.

The dashboard chart renderer is loaded through the shared Vite bundle but initializes only when the dashboard root is present. Livewire period selection sends updated fixture data to that page's sales chart; no chart values are presented as persisted sales.

## VAT demonstration pages

The authenticated `/tax/vat-summary` Livewire page selects monthly or quarterly periods from `VatDemonstrationData::summaries()`, displaying the existing fixture totals without persisting or calculating business activity. It links back to VAT Records, and both pages label their fixtures as illustrative and non-filing.

## Accounting overview demonstration

The authenticated `/accounting` route uses the Accounting-grouped `AccountingOverview` Livewire page and shared application shell. It labels revenue, expenses, net income, and payables as unavailable, and its recent-entry state does not treat session demo journals or POS sales as postings. Quick actions link only to registered destination routes; unavailable pages remain disabled until implemented. This page does not add accounting calculations or records and does not change the accounting release prerequisites above.

## Cash Disbursements demonstration

The authenticated `/accounting/cash-disbursements` Livewire page uses the shared application shell and keeps reference/payee search and payment-method selection interactive over an empty dataset. Payment totals and counts remain unavailable; disbursement maintenance, payment processing, cash movement, and accounting postings are not implemented.

## Financial Statements demonstration

The authenticated `/accounting/financial-statements` Livewire page offers Income Statement and Balance Sheet selectors with date controls. Its selected document shows the selected title, dates, and an explicit limitation; no account mapping, accounting policy, financial calculation, session journal, POS sale, or persisted business data is used. Print styles isolate the document from the application shell.
