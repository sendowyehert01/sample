<?php

use Core\Application;
use Core\Container;
use Core\HttpRouter;
use Core\Request;
use Http\Controllers\ApiController;
use Http\Controllers\SampleController;
use Http\Validation\SampleValidator;
use Illuminate\Database\Capsule\Manager as Capsule;
use Repositories\SampleRepository;

require_once __DIR__ . '/autoload.php';

if (is_file(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
}

$config = require __DIR__ . '/config.php';

$capsule = new Capsule();
$capsule->addConnection($config['database']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container = new Container();

$container->bind(Request::class, static fn (): Request => Request::fromGlobals());
$container->bind(SampleValidator::class, static fn (): SampleValidator => new SampleValidator());
$container->bind(ApiController::class, static fn (): ApiController => new ApiController());

$container->bind(SampleRepository::class, static fn (): SampleRepository => new SampleRepository());

$container->bind(SampleController::class, static fn (Container $container): SampleController => new SampleController(
    $container->resolve(SampleRepository::class),
    $container->resolve(Request::class),
    $container->resolve(SampleValidator::class)
));

$container->bind(HttpRouter::class, static function (Container $container): HttpRouter {
    $router = new HttpRouter($container);
    $registerRoutes = require __DIR__ . '/routes.php';
    $registerRoutes($router);

    return $router;
});

$container->bind(Application::class, static fn (Container $container): Application => new Application(
    $container->resolve(HttpRouter::class),
    $container->resolve(Request::class)
));

return $container;
