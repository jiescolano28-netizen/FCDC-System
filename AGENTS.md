# Repository Guidelines

## Project Overview

FCDC is a Laravel application for a construction company selling second-hand building materials. It provides employee login and registration plus an inventory dashboard. Treat the dashboard's VAT, reports, and accounting panels as frontend/sample functionality unless a backend implementation exists; do not assume they are persisted.

## Architecture & Data Flow

The backend follows Laravel's MVC/request flow: `public/index.php` boots `bootstrap/app.php`, which loads `routes/web.php`; routes return Blade views or dispatch to controllers. `LoginController` and `RegisterController` implement employee session authentication. `InventoryController` validates inventory requests, persists through Eloquent, and returns JSON; the dashboard's inline JavaScript calls those inventory endpoints. `Employee` and `Inventory` are the application models. Vite builds shared CSS/JS assets, while much of the dashboard UI, CSS, and JavaScript currently lives in `resources/views/dashboard.blade.php`.

## Command Reference

- Use `php artisan` for Laravel commands (e.g., `php artisan migrate`, `php artisan test`). 
- When create `controller`, `model`, or `migration` use `php artisan make:controller`, `php artisan make:model`, or `php artisan make:migration` respectively.

## Key Directories

- `app/Http/Controllers/` — HTTP request handling.
- `app/Models/` — Eloquent models for employees and inventory.
- `routes/` — web and console routes.
- `resources/views/` — Blade pages, including the dashboard.
- `resources/js/`, `resources/css/` — Vite entry assets and shared frontend setup/styles.
- `database/migrations/`, `database/seeders/`, `database/factories/` — schema and database support.
- `tests/Feature/`, `tests/Unit/` — Pest feature and unit suites.
- `docs/agents/` — issue-tracker, triage-label, and domain-document guidance; follow the relevant file when using those workflows.

## Development Commands

- `composer setup` — install PHP and npm dependencies, initialize `.env` and app key, migrate, and build assets. It expects a usable database for migrations.
- `composer dev` — run the Laravel server, queue listener, and Vite dev server together.
- `php artisan serve` — run only the Laravel HTTP server.
- `npm run dev` / `npm run build` — run Vite in development / build frontend assets.
- `composer test` or `php artisan test` — run the test suite; the Composer script clears Laravel's config cache first.
- `vendor/bin/pint` — run Laravel Pint, which is a development dependency; no dedicated lint script is defined.

## Code Conventions & Common Patterns

- Keep HTTP concerns in controllers and route declarations in `routes/web.php`; use Laravel request validation before persistence, following the registration and inventory handlers.
- Persist application records with Eloquent models. Declare mass-assignable fields in `$fillable` and explicit decimal/date casts in `$casts` where needed, following `Inventory`.
- Authentication uses Laravel's session guard and the `Employee` provider. Preserve the existing session lifecycle for login/logout; hash passwords with Laravel's `Hash` facade.
- Return Blade views for page routes and JSON responses for inventory endpoints. Keep endpoint changes aligned with the dashboard's `/inventory` fetch requests.
- Follow Laravel/PHP conventions already present: PSR-4 `App\\` classes under `app/`, class names in PascalCase, methods/properties in camelCase, and four-space PHP indentation. Frontend modules use ES modules.
- The dashboard is currently a large self-contained Blade page. Check for existing inline UI/API behavior before moving or changing it; keep UI and endpoint contracts synchronized.

## Important Files

- `bootstrap/app.php` — Laravel routing/bootstrap configuration.
- `routes/web.php` — homepage, login/register/logout, dashboard, and inventory routes.
- `app/Http/Controllers/LoginController.php`, `RegisterController.php`, `InventoryController.php` — primary request handlers.
- `app/Models/Employee.php`, `Inventory.php` — application persistence/auth models.
- `resources/views/dashboard.blade.php` — dashboard UI and inventory client behavior.
- `composer.json`, `package.json`, `vite.config.js` — dependency and command definitions.
- `phpunit.xml`, `tests/Pest.php` — test suites and Pest setup.

## Runtime/Tooling Preferences

Use Composer for PHP dependencies and npm for frontend dependencies. The project requires PHP `^8.2` and Laravel `^12`; the locked Vite version requires Node `^20.19.0` or `>=22.12.0`. Follow the committed `composer.lock` and `package-lock.json`. `.env.example` defaults to MySQL and database-backed session/cache/queue; PHPUnit overrides these with in-memory SQLite and array/sync test drivers. Do not copy environment secrets into code or documentation.

## Testing & QA

Pest 3 with the Laravel plugin runs through Laravel's Artisan test runner. PHPUnit defines `Unit` and `Feature` suites under `tests/`; feature tests use `Tests\\TestCase`. Add tests in the matching suite and exercise observable behavior, especially route responses, validation, persistence, and auth boundaries. The current suite contains only framework example/smoke tests and has no configured coverage threshold, so do not treat it as comprehensive domain coverage. SQLite-backed tests require the PHP SQLite PDO extension.

## Agent Workflow References

- Issues and feature specs belong under `.scratch/`; follow `docs/agents/issue-tracker.md` for file layout and status conventions.
- Use the canonical triage labels in `docs/agents/triage-labels.md`.
- Before domain-modeling work, consult `docs/agents/domain.md` for glossary and ADR lookup rules. If the referenced glossary or ADRs do not exist, proceed without creating them preemptively.

## Agent skills

### Issue tracker

Issues and specs are local Markdown files under `.scratch/`. See `docs/agents/issue-tracker.md`.

### Domain docs

Use the single-context layout. See `docs/agents/domain.md`.
