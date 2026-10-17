<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Local development with Laravel Sail

This repository's local environment uses Laravel Sail with PHP 8.4, Node 22, and
MySQL 8.4. The application is available at
[http://localhost:8080](http://localhost:8080), Vite at
[http://localhost:5173](http://localhost:5173), and MySQL is private to the
Compose network (it is not published on the host).

Before the first run, ensure Docker Desktop is running and Ubuntu is enabled
under Docker Desktop's WSL integration settings. Then run these commands from
an Ubuntu WSL shell in the existing working copy:

```shell
cd /mnt/c/Users/Admin/.others/fcdc

# The first command only needs to be run when vendor/ is not present.
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -e COMPOSER_PROCESS_TIMEOUT=1200 \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

test -f .env || cp .env.example .env
./vendor/bin/sail build
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
```

The committed `.env.example` contains disposable local-development MySQL
credentials (`sail` / `password`) and fixed container IDs so these commands
also work from a root WSL shell. Replace them in your uncommitted `.env` when
needed; never copy production credentials into `.env.example`.

The bootstrap timeout allows Composer archive extraction on the slower
Windows-mounted filesystem. If an install is interrupted, rerun the bootstrap
command; `composer install` resumes the locked dependencies without updating them.

For a normal work session, start the containers and run Vite and the queue
listener in separate WSL terminals:

```shell
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan queue:work --tries=1
```

Run the test suite in another terminal with
`./vendor/bin/sail test`. PHPUnit forces `APP_ENV=testing`, SQLite `:memory:`,
and the activity log connection in both environment variables and `$_SERVER`;
keep both PHPUnit entries so inherited Windows environment values cannot
override test isolation. Tests use array-backed sessions and never use the
development MySQL database. Stop the services with
`./vendor/bin/sail stop`; this preserves the project-scoped `fcdc_sail-mysql`
volume.

Do not use `docker compose down -v` unless you intentionally want to erase the
local database.

## Employee and role management

The application authenticates `App\Models\Employee` with the `web` guard.
Employees can have multiple Spatie roles. Run migrations and seed the fixed
permission catalog, then provision the first administrator interactively:

```shell
php artisan migrate
php artisan db:seed
php artisan app:provision-administrator
```

The provisioning command prompts for a unique username, email, and password:
at least 12 characters, including uppercase and lowercase letters, a number,
and a symbol. No additional mix is required. It creates a dedicated employee
with the protected `System Administrator` role and refuses to run if an active
administrator already exists. It does not use the demo `testuser` account or
store a password in seed data. Keep the command available only to trusted
deployment operators.

The `employees` and `roles` pages require `employees.view` and `roles.view`.
The fixed catalog grants per-action employee and role administration
permissions; role assignment additionally requires `employees.assign-roles`.
New employees receive no role automatically. Administrators assign one or
more roles explicitly. The role manager can only assign catalog permissions;
the protected administrator role cannot be edited or deleted.

Employees are soft deleted and can be restored from the deleted-employees view.
Their unique usernames and emails remain reserved, and their existing role
assignments are retained. Assigned roles cannot be deleted, including roles
held by soft-deleted employees. The last active administrator cannot be
deleted or stripped of its administrator role through employee management.

The permission catalog gates Employee, Role, and Inventory management.
Inventory batch categories are managed from Inventory → Batch categories and
use the inventory view, create, update, and delete permissions. Inventory items
can optionally select an active batch category when created or edited; leaving
the field empty means the item has no batch category. POS and Settings continue
to use their existing access rules.

## Isolated demo chart of accounts

The 54-account proposed chart is not included in ordinary `php artisan db:seed`.
Seed only after provisioning a dedicated nonproduction target and configuring the
demo connection and allowlist. Run `php artisan demo:verify-isolation` to verify
the effective write target; then run `php artisan demo:seed-proposed-chart`.
The seed command repeats the isolation check, inserts definitions transactionally,
and refuses conflicting definitions without changing the batch.

## Complete synthetic demo setup

`php artisan demo:setup --env=demo` migrates and populates a **dedicated demo
database**. It does not change your normal `.env` or populate a real production
database. The isolation checks also apply when invoking `FullDemoSeeder` directly
from `db:seed` or Tinker. Ordinary `php artisan db:seed` remains production-safe:
it seeds the permission catalog only.

### First run (Windows PowerShell)

From the repository root:

```powershell
Copy-Item .env.demo.example .env.demo
New-Item -ItemType Directory -Force storage/app/demo
New-Item -ItemType File storage/app/demo/fcdc-demo.sqlite
```

Set `DEMO_DB_DATABASE` in `.env.demo` to the **absolute path** of that new SQLite
file. Keep it beneath this repository's `storage/app/demo` directory and do not
point any other database connection at it. Do not overwrite an existing `.env.demo`
or SQLite file when rerunning setup.

Environment variables inherited from your terminal take precedence over env files.
Select the demo target explicitly in this terminal before running Artisan:

```powershell
$env:APP_ENV = "demo"
$env:DB_CONNECTION = "demo"
$env:ACTIVITY_LOGGER_DB_CONNECTION = "demo"
$env:SESSION_DRIVER = "file"
$env:CACHE_STORE = "file"
$env:QUEUE_CONNECTION = "sync"
$env:DB_QUEUE_CONNECTION = "demo"
$env:DB_QUEUE_FAILED_CONNECTION = "demo"
$env:DB_QUEUE_BATCHING_CONNECTION = "demo"
php artisan key:generate --env=demo
php artisan demo:setup --env=demo
php artisan serve --env=demo --host=127.0.0.1 --port=8001
```

Open <http://127.0.0.1:8001/login>. Login uses **email**, not username.
The setup prints one randomly generated initial password shared by these
demo-only accounts; save it when first displayed. It is not reset or printed on
reruns. For the demo prepared in this working copy, login details are saved in
the ignored `storage/app/demo/credentials.txt`.

| Email | Access |
| --- | --- |
| `demo.admin@example.test` | All catalog permissions |
| `demo.accountant@example.test` | Accounting, reports, tax, and inventory viewing |
| `demo.cashier@example.test` | POS checkout and inventory viewing |
| `demo.warehouse@example.test` | Inventory, valuation, and demolition recovery |
| `demo.viewer@example.test` | Read-only reports, accounting, tax, inventory, and projects |

Close this terminal or clear these environment overrides before operating on a
different database. If configuration has been cached, select the intended
configuration-cache path explicitly; do not reuse a production configuration cache
for demo setup. The guard rejects an effective write connection other than `demo`.

### Included workflows

- The complete 54-account proposed chart plus a demo card-clearing account.
  Only accounts used by the sample workflows are activated and synthetically
  approved. Policy-held accounts remain inactive.
- Approved demo mappings, opening stock valuation, balanced opening journal,
  supplier opening invoices, and pre-cutover YTD summary when required.
- Six inventory items and batch categories, including an out-of-stock item.
- Three suppliers, a posted inventory purchase, an outstanding partial payment,
  a purchase draft, a posted utility expense, and an expense draft.
- Two demolition projects: one recovery assessed with accepted/rejected
  quantities and a valued receipt, and another awaiting assessment.
- Cash, card, and bank-transfer POS sales with stock/cost effects, receipt lines,
  and linked VAT records.
- A posted manual journal, a balanced draft, and an intentionally unbalanced
  draft for testing correction and posting validation.

Cutover and posted transactions use the setup day's Manila date. Opening
invoices include overdue balances; new purchase drafts include future due dates.
The setup simulates readiness-gated accounting activation **only in the isolated
demo**, making ledger and financial reports available. None of its balances,
policy approvals, evidence descriptions, or activation is valid production evidence.

Business records are seeded in one transaction through the existing workflows.
On failure, seed changes roll back; migrations remain applied. On a completed
rerun, existing records, user changes, and passwords are preserved. A nonempty
business database without the completion journal is rejected instead of merged
or reset. To make a second independent dataset, provision another empty isolated
demo database; do not run `migrate:fresh` against a working database.

Use the seeded pages to try POS checkout, inventory receiving, assessment of the
pending recovery, posting of drafts, invoice settlement, and linked corrections.
The unbalanced journal must be corrected before posting. Check Trial Balance,
General Ledger, Financial Statements, and VAT records after each operation.
Receipt attachments still require real uploaded test files; the seeder does not
invent supporting documents. Company settings currently remain session-local,
not persisted company master data.

Regression checks:

```shell
php artisan test tests/Feature/FullDemoSetupTest.php tests/Feature/DemoDatabaseIsolationTest.php tests/Feature/ProposedChartOfAccountsSeederTest.php
```

### Real production setup (no sample transactions)

Use a separate database, production credentials, a private application key,
`APP_ENV=production`, `APP_DEBUG=false`, and `DEMO_ENABLED=false`.
Back up any existing target before migrations. Run:

```shell
php artisan migrate --force
php artisan db:seed --force
php artisan app:provision-administrator
```

Then use the application with actual authorized preparers and reviewers:

1. Create employees and assign appropriate roles; do not reuse demo accounts.
2. Enter the business's reviewed chart and approve eligible accounts and posting
   mappings. Proposed classifications are not automatic accounting policy.
3. Enter actual inventory counts, suppliers, and opening unpaid invoices.
4. Prepare and approve the cutover stock valuation and balanced opening journal,
   reconciling Inventory and Accounts Payable to their supporting schedules.
5. For a midyear cutover, prepare and approve supported YTD balances through the
   previous day. Reconcile any existing post-cutover source transactions.
6. Resolve all Opening Books readiness blockers, then have an authorized user
   activate the books. Verify a real controlled transaction and its reports.

Production deployment still needs environment-specific HTTPS, storage access,
backups/restores, email, queue processing where used, and reviewed company/tax
policies. The sample setup is a testing aid, not certification of production or
tax readiness.


## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
