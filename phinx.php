<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$database = (require __DIR__ . '/config.php')['database'];

$environment = [
    'adapter' => $database['driver'],
    'name' => $database['database'],
    'table_prefix' => $database['prefix'],
];

if ($database['driver'] === 'mysql') {
    $environment += [
        'host' => $database['host'],
        'port' => $database['port'],
        'user' => $database['username'],
        'pass' => $database['password'],
        'charset' => $database['charset'],
        'collation' => $database['collation'],
    ];
} elseif ($database['driver'] === 'sqlite') {
    // Phinx otherwise appends .sqlite3, while Eloquent uses the exact filename.
    $environment['suffix'] = '';
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
    ],
    'environments' => [
        'default_migration_table' => $database['prefix'] . 'phinxlog',
        'default_environment' => 'development',
        'development' => $environment,
    ],
    'version_order' => 'creation',
];
