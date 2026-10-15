<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'app.env' => 'production',
        'database.default' => 'sqlite',
        'activitylog.database_connection' => 'sqlite',
        'database.connections.demo' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    config([
        'demo.enabled' => false,
        'demo.allowed_environments' => [],
        'demo.allow_in_memory_test' => false,
    ]);
});

test('demo isolation rejects production even when explicitly enabled and allowlisted', function () {
    config(['app.env' => 'production']);
    config(['demo.enabled' => true, 'demo.allowed_environments' => ['production']]);

    $this->artisan('demo:verify-isolation')
        ->assertExitCode(1);

    expect(DB::connection('sqlite')->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('sqlite');
});

test('demo isolation rejects missing enablement and a business default connection', function () {
    config(['app.env' => 'demo', 'database.default' => 'sqlite']);
    config(['demo.allowed_environments' => ['demo']]);

    $this->artisan('demo:verify-isolation')->assertExitCode(1);

    config(['demo.enabled' => true]);
    $this->artisan('demo:verify-isolation')->assertExitCode(1);
});

test('demo isolation permits only an explicitly isolated in-memory test target', function () {
    config([
        'app.env' => 'testing',
        'database.default' => 'demo',
        'activitylog.database_connection' => 'demo',
    ]);
    config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing'], 'demo.allow_in_memory_test' => true]);

    $this->artisan('demo:verify-isolation')->assertExitCode(0);
});

test('demo isolation rejects activity logging on a different connection', function () {
    config([
        'app.env' => 'testing',
        'database.default' => 'demo',
        'activitylog.database_connection' => 'sqlite',
    ]);
    config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing'], 'demo.allow_in_memory_test' => true]);

    $this->artisan('demo:verify-isolation')->assertExitCode(1);
});

test('demo isolation verifies the actual canonical persistent sqlite file', function () {
    $directory = storage_path('app/demo');
    if (! is_dir($directory)) {
        mkdir($directory, 0770, true);
    }

    $databasePath = $directory.DIRECTORY_SEPARATOR.'isolation-test.sqlite';
    touch($databasePath);
    $canonicalPath = realpath($databasePath);

    try {
        config([
            'app.env' => 'demo',
            'database.default' => 'demo',
            'activitylog.database_connection' => 'demo',
            'database.connections.demo.database' => $databasePath,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        config(['demo.enabled' => true, 'demo.allowed_environments' => ['demo'], 'demo.allow_in_memory_test' => false]);

        DB::purge('demo');
        $this->artisan('demo:verify-isolation')->assertExitCode(0);

        expect(DB::connection('demo')->getPdo()->query('PRAGMA database_list')->fetchAll()[0]['file'])->toBe($canonicalPath);
    } finally {
        DB::purge('demo');
        @unlink($databasePath);
    }
});

test('demo isolation rejects a persistent sqlite file outside the dedicated directory', function () {
    $databasePath = database_path('isolation-test.sqlite');
    touch($databasePath);

    try {
        config([
            'app.env' => 'demo',
            'database.default' => 'demo',
            'activitylog.database_connection' => 'demo',
            'database.connections.demo.database' => $databasePath,
        ]);
        config(['demo.enabled' => true, 'demo.allowed_environments' => ['demo']]);

        $this->artisan('demo:verify-isolation')->assertExitCode(1);
    } finally {
        @unlink($databasePath);
    }
});

test('demo isolation rejects another sqlite alias for the same persistent target', function () {
    $directory = storage_path('app/demo');
    if (! is_dir($directory)) {
        mkdir($directory, 0770, true);
    }

    $databasePath = $directory.DIRECTORY_SEPARATOR.'alias-test.sqlite';
    touch($databasePath);

    try {
        config([
            'app.env' => 'demo',
            'database.default' => 'demo',
            'activitylog.database_connection' => 'demo',
            'database.connections.demo.database' => $databasePath,
            'database.connections.sqlite.database' => $databasePath,
        ]);
        config(['demo.enabled' => true, 'demo.allowed_environments' => ['demo']]);

        $this->artisan('demo:verify-isolation')->assertExitCode(1);
    } finally {
        @unlink($databasePath);
    }
});

test('demo isolation rejects database-backed session writes through a business connection', function () {
    config([
        'app.env' => 'testing',
        'database.default' => 'demo',
        'activitylog.database_connection' => 'demo',
        'session.driver' => 'database',
        'session.connection' => 'sqlite',
    ]);
    config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing'], 'demo.allow_in_memory_test' => true]);

    $this->artisan('demo:verify-isolation')->assertExitCode(1);
});

test('demo isolation rejects server databases without provisioning evidence', function () {
    config([
        'app.env' => 'demo',
        'database.default' => 'demo',
        'activitylog.database_connection' => 'demo',
        'database.connections.demo' => [
            'driver' => 'mysql',
            'database' => 'demo_books',
            'host' => 'demo-db.internal',
            'port' => '3306',
            'username' => 'demo',
            'password' => 'secret',
            'url' => null,
            'unix_socket' => null,
        ],
        'demo.enabled' => true,
        'demo.allowed_environments' => ['demo'],
    ]);

    $this->artisan('demo:verify-isolation')->assertExitCode(1);
});

test('demo isolation rejects a business database URL alias to the same server database', function () {
    config([
        'app.env' => 'demo',
        'database.default' => 'demo',
        'activitylog.database_connection' => 'demo',
        'database.connections.demo' => [
            'driver' => 'mysql',
            'database' => 'demo_books',
            'host' => 'demo-db.internal',
            'port' => '3306',
            'username' => 'demo',
            'password' => 'secret',
            'url' => null,
            'unix_socket' => null,
        ],
        'database.connections.mysql' => [
            'driver' => 'mysql',
            'url' => 'mysql://business:secret@demo-db.internal:3306/demo_books',
            'host' => 'business-db.internal',
            'port' => '3306',
            'database' => 'business_books',
        ],
        'demo.enabled' => true,
        'demo.allowed_environments' => ['demo'],
        'demo.database_isolation_attested' => true,
        'demo.expected_host' => 'demo-db.internal',
        'demo.expected_port' => '3306',
        'demo.expected_database' => 'demo_books',
        'demo.expected_server_id' => 'server-1',
    ]);

    $this->artisan('demo:verify-isolation')->assertExitCode(1);
});
