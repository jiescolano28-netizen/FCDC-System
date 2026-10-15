# 01: Verify demo database isolation

**What to build:** An explicitly invoked demo setup can use only a persistent database independently verified as isolated from business records. Production, unknown, shared, or ambiguous targets are rejected before writes, without changing business database configuration.

**Blocked by:** None (can start immediately)

**Status:** implementation-complete; persistent deployment provisioning remains a prerequisite

- [x] Demo setup requires explicit enablement and an explicit nonproduction environment allowlist; production and unknown environments are rejected regardless of flags or command options.
- [x] The actual write target and effective database identity are independently verified, including relevant overrides and aliases; unverified, shared, or mismatched targets fail closed before account writes or setup operations.
- [x] The application and audit activity for a demo run use the isolated target; no demo seeding is executed on a persistent target without sufficient isolation evidence.

**Implementation:** `demo:verify-isolation` checks explicit `DEMO_ENABLED=true`, `DEMO_ALLOWED_ENVIRONMENTS`, `DB_CONNECTION=demo`, and audit-activity routing before setup. SQLite must be an existing canonical file below `storage/app/demo`, and it must not be aliased by another configured SQLite connection. Server databases require exact configured and live server/database identity matches plus `DEMO_DB_ISOLATION_ATTESTED=true`, representing provisioning evidence of dedicated resources and restricted credentials. Database-backed session, cache, and queue connections must use the demo default. No business database configuration was changed.

**Verification:** `php artisan test --compact tests/Feature/DemoDatabaseIsolationTest.php` — 10 passed, 13 assertions. This exercises production and disabled-mode rejection, business-default and audit/session connection rejection, explicitly isolated in-memory testing, persistent SQLite identity, unsafe path rejection, connection aliases, and a URL override alias. `php artisan demo:verify-isolation` on the current workstation failed closed because the environment is not allowlisted. No persistent demo database was configured or seeded.

**Provisioning prerequisite:** provide a dedicated persistent target before seeding. For SQLite, provision the database file under `storage/app/demo` and configure `APP_ENV=demo`, `DEMO_ENABLED=true`, `DEMO_ALLOWED_ENVIRONMENTS=demo`, `DB_CONNECTION=demo`, `ACTIVITY_LOGGER_DB_CONNECTION=demo`, and `DEMO_DB_DATABASE` to its canonical file path. For MySQL/MariaDB/PostgreSQL, also configure the `DEMO_DB_*` endpoint and identity values and set the provisioning attestation only after separately verifying dedicated resources and credentials restricted to that resource. Then run `php artisan demo:verify-isolation`. Do not run demo migrations or seeding before successful verification.
