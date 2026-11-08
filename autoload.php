<?php

$vendor = __DIR__ . '/vendor/autoload.php';

if (is_file($vendor)) {
    require $vendor;
}

const BASE_PATH = __DIR__ . '/';

spl_autoload_register(static function (string $class): void {
    $path = BASE_PATH . str_replace('\\', '/', $class) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
