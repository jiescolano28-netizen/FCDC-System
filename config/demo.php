<?php

return [
    'enabled' => filter_var(env('DEMO_ENABLED', false), FILTER_VALIDATE_BOOL),
    'allowed_environments' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DEMO_ALLOWED_ENVIRONMENTS', '')),
    ))),
    'allow_in_memory_test' => filter_var(env('DEMO_ALLOW_IN_MEMORY_TEST', false), FILTER_VALIDATE_BOOL),
    'database_isolation_attested' => filter_var(env('DEMO_DB_ISOLATION_ATTESTED', false), FILTER_VALIDATE_BOOL),
    'expected_host' => env('DEMO_DB_EXPECTED_HOST'),
    'expected_port' => env('DEMO_DB_EXPECTED_PORT'),
    'expected_database' => env('DEMO_DB_EXPECTED_DATABASE'),
    'expected_server_id' => env('DEMO_DB_EXPECTED_SERVER_ID'),
];
