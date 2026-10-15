# 01: Verify demo database isolation

**What to build:** An explicitly invoked demo setup can use only a persistent database independently verified as isolated from business records. Production, unknown, shared, or ambiguous targets are rejected before writes, without changing business database configuration.

**Blocked by:** None (can start immediately)

**Status:** implementation-complete; persistent deployment provisioning remains a prerequisite

- [x] Demo setup requires explicit enablement and an explicit nonproduction environment allowlist; production and unknown environments are rejected regardless of flags or command options.
- [x] The actual write target and effective database identity are independently verified, including relevant overrides and aliases; unverified, shared, or mismatched targets fail closed before account writes or setup operations.
- [x] The application and audit activity for a demo run use the isolated target; no demo seeding is executed on a persistent target without sufficient isolation evidence.

**Implementation:** `demo:verify-isolation` checks explicit `DEMO_ENABLED=true`, `DEMO_ALLOWED_ENVIRONMENTS`, `DB_CONNECTION=demo`, and audit-activity routing before setup. SQLite must be an existing canonical file below `storage/app/demo`, and it must not be aliased by another configured SQLite connection. Split read/write endpoints and ambiguous URL/socket aliases are rejected. Server databases require exact configured and live server/database identity matches plus `DEMO_DB_ISOLATION_ATTESTED=true`, representing provisioning evidence of dedicated resources and restricted credentials. Database-backed sessions, caches, queue dispatch, failed-job storage, and batch metadata must use the demo connection or its default. No business database configuration was changed.

**Verification:** `php artisan test --compact tests/Feature/DemoDatabaseIsolationTest.php` — 14 passed, 17 assertions, including queue failed-job/batch targets, split read/write endpoints and socket aliases. `php artisan demo:verify-isolation` on the current workstation failed closed because the environment is not allowlisted. No persistent demo database was configured or seeded.

**Full suite:** `php artisan test --compact` — 264 passed, 4 failed (2,454 assertions). Failures: `ChartOfAccountsTest` expects “No accounts match the selected filters” while the page renders “No accounts found”; `FiscalYearClosingTest` and `ProductionAccountingActivationTest` encounter unavailable production activation; `ValuedStockMovementTest` refers to undeclared Livewire property `stockItemId`. The full-suite run preceded the follow-up isolation hardening; the focused suite passed afterward.

**Provisioning prerequisite:** provide a dedicated persistent target before seeding. For SQLite, provision the database file under `storage/app/demo` and configure `APP_ENV=demo`, `DEMO_ENABLED=true`, `DEMO_ALLOWED_ENVIRONMENTS=demo`, `DB_CONNECTION=demo`, `ACTIVITY_LOGGER_DB_CONNECTION=demo`, and `DEMO_DB_DATABASE` to its canonical file path. For MySQL/MariaDB/PostgreSQL, also configure the `DEMO_DB_*` endpoint and identity values and set the provisioning attestation only after separately verifying dedicated resources and credentials restricted to that resource. Then run `php artisan demo:verify-isolation`. Do not run demo migrations or seeding before successful verification.
