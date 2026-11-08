<?php

namespace Core;

class HttpRouter
{
    private array $routes = [];

    public function __construct(private Container $container)
    {
    }

    public function add(string $method, string $uri, array $action): self
    {
        $this->routes[] = ['method' => strtoupper($method), 'uri' => $uri, 'action' => $action];

        return $this;
    }

    public function get(string $uri, array $action): self
    {
        return $this->add('GET', $uri, $action);
    }

    public function post(string $uri, array $action): self
    {
        return $this->add('POST', $uri, $action);
    }

    public function put(string $uri, array $action): self
    {
        return $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, array $action): self
    {
        return $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, array $action): self
    {
        return $this->add('DELETE', $uri, $action);
    }

    public function route(string $uri, string $method): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            $parameters = $this->parameters($route['uri'], $uri);

            if ($parameters === null) {
                continue;
            }

            $allowed[] = $route['method'];

            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            [$class, $action] = $route['action'];
            $controller = $this->container->resolve($class);

            return $controller->{$action}(...$parameters);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.', [], ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404, 'Route not found.');
    }

    private function parameters(string $template, string $uri): ?array
    {
        $routeParts = explode('/', trim($template, '/'));
        $uriParts = explode('/', trim($uri, '/'));

        if (count($routeParts) !== count($uriParts)) {
            return null;
        }

        $parameters = [];

        foreach ($routeParts as $index => $part) {
            if (preg_match('/^\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $part)) {
                $parameters[] = rawurldecode($uriParts[$index]);
            } elseif ($part !== $uriParts[$index]) {
                return null;
            }
        }

        return $parameters;
    }
}
