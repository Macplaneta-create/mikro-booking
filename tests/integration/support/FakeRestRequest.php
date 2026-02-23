<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use ArrayAccess;

class FakeRestRequest implements ArrayAccess {
    private array $params;
    private array $routeParams;

    public function __construct(array $params = [], array $routeParams = []) {
        $this->params = $params;
        $this->routeParams = $routeParams;
    }

    public function get_params(): array {
        return $this->params;
    }

    public function get_param(string $key) {
        return $this->params[$key] ?? null;
    }

    public function offsetExists(mixed $offset): bool {
        return array_key_exists($offset, $this->routeParams) || array_key_exists($offset, $this->params);
    }

    public function offsetGet(mixed $offset): mixed {
        return $this->routeParams[$offset] ?? $this->params[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {
        $this->params[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void {
        unset($this->params[(string) $offset], $this->routeParams[(string) $offset]);
    }
}
