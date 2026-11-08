<?php

namespace Core;

use RuntimeException;

class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $class, callable $factory): void
    {
        $this->bindings[$class] = $factory;
        unset($this->instances[$class]);
    }

    public function resolve(string $class): object
    {
        if (isset($this->instances[$class])) {
            return $this->instances[$class];
        }

        if (!isset($this->bindings[$class])) {
            throw new RuntimeException("No service registered for {$class}.");
        }

        return $this->instances[$class] = ($this->bindings[$class])($this);
    }
}
