<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Ports;

use Illuminate\Http\Request;
use Zolta\Http\Request\Contracts\RequestPort;

/**
 * Adapter between Laravel's Request and Core RequestPort.
 */
final readonly class LaravelRequestPort implements RequestPort
{
    public function __construct(private Request $request) {}

    public function all(): array
    {
        return $this->request->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->request->all(), $key, $default);
    }

    public function merge(array $data): void
    {
        $this->request->merge($data);
    }

    public function user(): mixed
    {
        return $this->request->user();
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->request->header($key, $default);
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->request->route($key, $default);
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        return $this->request->query($key, $default);
    }
}
