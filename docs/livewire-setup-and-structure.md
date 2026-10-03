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

The design is approved. Implementation may proceed for the approved scope, but the application must not be released as the requested full application until the accounting rules are supplied and implemented. Until then, accounting remains deferred. Needed inputs include the chart of accounts, sales/payables/payment posting rules, payable and disbursement lifecycles, and report definitions.

## Implemented authenticated shell and Settings slice

Migrated Livewire pages use `layouts.app` with the extracted shell assets in `resources/css/shell.css` and `resources/js/shell.js`. The current shell links only to supported destinations: Dashboard, inventory management, and Settings.

Settings values are temporary session state, not business settings. The session key `demo.settings.employee.{employee-id}` scopes values to both the authenticated employee and their session; logout invalidates the session. Later demo-only state should use the same `demo.{area}.employee.{employee-id}` ownership and session-lifetime convention. Do not add storage APIs or placeholder features for those future areas.
