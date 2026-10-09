<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'app'),
            'username' => env('DB_USERNAME', 'app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],
        // Only used by the local framework preflight; deployed acceptance uses MySQL.
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', ':memory:'),
                     'prefix' => '', 'foreign_key_constraints' => true],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
];
