<?php

use Core\HttpRouter;
use Http\Controllers\ApiController;
use Http\Controllers\SampleController;

return static function (HttpRouter $router): void {
    $router->get('/', [ApiController::class, 'index']);

    $router->get('/samples', [SampleController::class, 'index']);
    $router->post('/samples', [SampleController::class, 'store']);
    $router->get('/samples/{id}', [SampleController::class, 'show']);
    $router->put('/samples/{id}', [SampleController::class, 'update']);
    $router->patch('/samples/{id}', [SampleController::class, 'update']);
    $router->delete('/samples/{id}', [SampleController::class, 'destroy']);
};
