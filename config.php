<?php

if (is_file(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$env = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return $value === false || $value === null || $value === '' ? $default : (string) $value;
};

return [
    'database' => [
        'driver' => $env('DB_DRIVER', 'mysql'),
        'host' => $env('DB_HOST', 'localhost'),
        'port' => (int) $env('DB_PORT', '3306'),
        'database' => $env('DB_DATABASE', 'crud_demo'),
        'username' => $env('DB_USERNAME', 'root'),
        'password' => $env('DB_PASSWORD', ''),
        'charset' => $env('DB_CHARSET', 'utf8mb4'),
        'collation' => $env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        'prefix' => $env('DB_PREFIX', ''),
    ],
];
