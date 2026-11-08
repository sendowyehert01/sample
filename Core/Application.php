<?php

namespace Core;

use Throwable;

class Application
{
    public function __construct(private HttpRouter $router, private Request $request)
    {
    }

    public function handle(): Response
    {
        try {
            return $this->router->route($this->request->path(), $this->request->method());
        } catch (HttpException $error) {
            return Response::json($error->payload(), $error->status(), $error->headers());
        } catch (Throwable $error) {
            error_log((string) $error);

            return Response::json(['message' => 'Internal server error.'], 500);
        }
    }

    public function run(): void
    {
        $this->handle()->send();
    }
}
