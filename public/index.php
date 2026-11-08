<?php

use Core\Application;

$container = require dirname(__DIR__) . '/bootstrap.php';
$container->resolve(Application::class)->run();
