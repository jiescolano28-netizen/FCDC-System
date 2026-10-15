<?php

namespace App\Support;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoDatabaseIsolation
{
    public function verify(): void
    {
        $environment = (string) config('app.env');
        $allowedEnvironments = config('demo.allowed_environments', []);

        if ($environment === '' || in_array($environment, ['production', 'prod'], true)
            || ! in_array($environment, $allowedEnvironments, true)) {
            throw new RuntimeException('The current environment is not explicitly allowlisted for demo setup.');
        }

        if (! config('demo.enabled', false)) {
            throw new RuntimeException('Demo setup is disabled. Set DEMO_ENABLED=true explicitly.');
        }

        if (config('database.default') !== 'demo') {
            throw new RuntimeException('The effective application write connection must be the dedicated demo connection.');
        }

        $activityConnection = config('activitylog.database_connection');
        if ($activityConnection !== null && $activityConnection !== 'demo') {
            throw new RuntimeException('Audit activity must use the dedicated demo connection.');
        }

        $connection = config('database.connections.demo');
        if (! is_array($connection) || empty($connection['driver'])) {
            throw new RuntimeException('The dedicated demo database connection is not configured.');
        }

        if (! empty($connection['url'])) {
            throw new RuntimeException('Database URL overrides are not accepted for the demo connection.');
        }

        if (array_key_exists('read', $connection) || array_key_exists('write', $connection)) {
            throw new RuntimeException('Separate read/write database endpoints are not accepted for demo setup.');
        }

        $this->verifyDatabaseBackedWrites();
        $driver = $connection['driver'];
        if ($driver === 'sqlite') {
            $this->verifySqlite($connection, $environment);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            $this->verifyServerDatabase($connection, $driver);

            return;
        }

        throw new RuntimeException('The configured demo database driver cannot be independently verified.');
    }

    private function verifyDatabaseBackedWrites(): void
    {
        if (config('session.driver') === 'database') {
            $this->requireDemoOrDefaultConnection('session.connection', config('session.connection'));
        }

        $cacheStore = config('cache.stores.'.config('cache.default'));
        if (($cacheStore['driver'] ?? null) === 'database') {
            $this->requireDemoOrDefaultConnection('cache.stores.database.connection', $cacheStore['connection'] ?? null);
            $this->requireDemoOrDefaultConnection('cache.stores.database.lock_connection', $cacheStore['lock_connection'] ?? null);
        }

        $queueName = (string) config('queue.default');
        $this->verifyQueueConnection($queueName, []);

        $failedQueue = config('queue.failed', []);
        if (in_array($failedQueue['driver'] ?? null, ['database', 'database-uuids'], true)) {
            $this->requireDemoOrDefaultConnection('queue.failed.database', $failedQueue['database'] ?? null);
        }

        $batching = config('queue.batching', []);
        $this->requireDemoOrDefaultConnection('queue.batching.database', $batching['database'] ?? null);
    }

    private function requireDemoOrDefaultConnection(string $setting, mixed $connection): void
    {
        if ($connection !== null && $connection !== '' && $connection !== 'demo') {
            throw new RuntimeException("Database-backed {$setting} must use the demo connection or Laravel's demo default.");
        }
    }

    private function verifyQueueConnection(string $name, array $visited): void
    {
        if (in_array($name, $visited, true)) {
            return;
        }

        $visited[] = $name;
        $queue = config("queue.connections.{$name}", []);
        if (($queue['driver'] ?? null) === 'database') {
            $this->requireDemoOrDefaultConnection("queue.connections.{$name}.connection", $queue['connection'] ?? null);
        }

        if (($queue['driver'] ?? null) === 'failover') {
            foreach ($queue['connections'] ?? [] as $connection) {
                $this->verifyQueueConnection((string) $connection, $visited);
            }
        }
    }

    private function verifySqlite(array $connection, string $environment): void
    {
        $database = $connection['database'] ?? null;
        if (! is_string($database) || $database === '') {
            throw new RuntimeException('The demo SQLite database path is missing.');
        }

        if ($database === ':memory:') {
            if ($environment !== 'testing' || ! config('demo.allow_in_memory_test', false)) {
                throw new RuntimeException('SQLite in-memory databases are accepted only for explicitly isolated tests.');
            }

            DB::connection()->getPdo();

            return;
        }

        $configuredPath = realpath($database);
        $demoDirectory = realpath(storage_path('app/demo'));
        if ($configuredPath === false || $demoDirectory === false
            || ! is_file($configuredPath)
            || ! str_starts_with($configuredPath, $demoDirectory.DIRECTORY_SEPARATOR)
            || $configuredPath !== $this->normalizePath($database)) {
            throw new RuntimeException('The demo SQLite file must be an existing canonical file beneath storage/app/demo.');
        }

        foreach (config('database.connections', []) as $name => $candidate) {
            if ($name === 'demo' || ($candidate['driver'] ?? null) !== 'sqlite') {
                continue;
            }

            $candidateDatabase = $this->connectionTarget($candidate)['database'];
            if (is_string($candidateDatabase) && $candidateDatabase !== ':memory:'
                && $this->normalizePath($candidateDatabase) === $configuredPath) {
                throw new RuntimeException('The demo SQLite file is also configured through another connection alias.');
            }
        }

        $pdo = DB::connection()->getPdo();
        $actualPath = $pdo->query('PRAGMA database_list')->fetchAll()[0]['file'] ?? null;
        if (! is_string($actualPath) || $this->normalizePath($actualPath) !== $configuredPath) {
            throw new RuntimeException('The connected SQLite database identity does not match its verified demo file.');
        }
    }

    private function verifyServerDatabase(array $connection, string $driver): void
    {
        if (! config('demo.database_isolation_attested', false)) {
            throw new RuntimeException('A provisioning attestation for dedicated server resources and restricted credentials is required.');
        }

        $configuredHost = (string) ($connection['host'] ?? '');
        if (! empty($connection['unix_socket']) || str_starts_with($configuredHost, '/') || strtolower($configuredHost) === 'localhost') {
            throw new RuntimeException('Socket-based or implicit-localhost demo database endpoints are not accepted.');
        }

        $expectedHost = config('demo.expected_host');
        $expectedPort = (string) config('demo.expected_port', '');
        $expectedDatabase = config('demo.expected_database');
        $expectedServerId = config('demo.expected_server_id');
        if (! is_string($expectedHost) || $expectedHost === '' || ! is_string($expectedDatabase) || $expectedDatabase === ''
            || ! is_string($expectedServerId) || $expectedServerId === ''
            || $configuredHost !== $expectedHost
            || (string) ($connection['port'] ?? '') !== $expectedPort
            || (string) ($connection['database'] ?? '') !== $expectedDatabase) {
            throw new RuntimeException('The demo server endpoint does not match its separately provisioned identity.');
        }

        foreach (config('database.connections', []) as $name => $candidate) {
            $candidateDriver = $candidate['driver'] ?? null;
            $sameServerDriver = $driver === $candidateDriver
                || (in_array($driver, ['mysql', 'mariadb'], true) && in_array($candidateDriver, ['mysql', 'mariadb'], true));
            if ($name === 'demo' || ! $sameServerDriver) {
                continue;
            }

            $candidateTarget = $this->connectionTarget($candidate);
            if ($candidateTarget['socket'] !== null && $candidateTarget['socket'] !== ''
                || is_string($candidateTarget['host']) && str_starts_with($candidateTarget['host'], '/')) {
                throw new RuntimeException('A configured socket-based database alias cannot be independently verified.');
            }

            if ($candidateTarget['database'] === $expectedDatabase) {
                throw new RuntimeException('A database alias with the demo database name makes server separation ambiguous.');
            }

        }

        $pdo = DB::connection()->getPdo();
        $identity = match ($driver) {
            'mysql', 'mariadb' => $pdo->query('SELECT DATABASE() AS database_name, @@hostname AS server_id, @@port AS port_number')->fetch(),
            'pgsql' => $pdo->query('SELECT current_database() AS database_name, inet_server_addr()::text AS server_id, inet_server_port() AS port_number')->fetch(),
        };

        if (! is_array($identity)
            || ($identity['database_name'] ?? null) !== $expectedDatabase
            || (string) ($identity['server_id'] ?? '') !== $expectedServerId
            || (string) ($identity['port_number'] ?? '') !== $expectedPort) {
            throw new RuntimeException('The connected server database identity does not match its provisioning identity.');
        }

    }

    private function connectionTarget(array $connection): array
    {
        $url = $connection['url'] ?? null;
        if (! is_string($url) || $url === '') {
            return [
                'host' => $connection['host'] ?? null,
                'port' => $connection['port'] ?? null,
                'database' => $connection['database'] ?? null,
                'socket' => $connection['unix_socket'] ?? null,
            ];
        }

        if (($connection['driver'] ?? null) === 'sqlite') {
            $parts = parse_url($url);
            if (! is_array($parts)) {
                throw new RuntimeException('A configured database URL alias cannot be independently verified.');
            }

            return [
                'host' => $parts['host'] ?? null,
                'port' => $parts['port'] ?? null,
                'database' => rawurldecode($parts['path'] ?? ''),
                'socket' => null,
            ];
        }

        $resolved = (new ConfigurationUrlParser)->parseConfiguration($connection);
        $host = $resolved['host'] ?? null;
        $socket = $resolved['unix_socket'] ?? null;
        if (is_string($host) && str_starts_with($host, '/')) {
            $socket = $host;
        }

        return [
            'host' => $host,
            'port' => $resolved['port'] ?? null,
            'database' => $resolved['database'] ?? null,
            'socket' => $socket,
        ];

    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath !== false) {
            return $realPath;
        }

        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
