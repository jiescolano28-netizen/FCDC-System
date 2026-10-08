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

The provisioning command prompts for a unique username, email, and password
(minimum 12 characters). It creates a dedicated employee with the protected
`System Administrator` role and refuses to run if an active administrator
already exists. It does not use the demo `testuser` account or store a password
in seed data. Keep the command available only to trusted deployment operators.

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

This permission catalog currently gates Employee and Role Management only.
Inventory, POS, and Settings continue to use their existing authenticated access
rules.

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
