<?php

declare(strict_types=1);

namespace Zolta\Http\Request;

use Zolta\Http\Request\Contracts\HttpRequestContract;
use Zolta\Support\ContainerRegistry;

final readonly class HttpRequest
{
    private HttpRequestContract $httpRequestContract;

    public function __construct()
    {
        $this->httpRequestContract = ContainerRegistry::resolve(HttpRequestContract::class);
    }

    // ── Input ────────────────────────────────────────────────────────────────

    public function all(): array
    {
        return $this->httpRequestContract->all();
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->input($key, $default);
    }

    public function only(array $keys): array
    {
        return $this->httpRequestContract->only($keys);
    }

    public function except(array $keys): array
    {
        return $this->httpRequestContract->except($keys);
    }

    public function has(string|array $key): bool
    {
        return $this->httpRequestContract->has($key);
    }

    public function filled(string|array $key): bool
    {
        return $this->httpRequestContract->filled($key);
    }

    // ── Query / Body ─────────────────────────────────────────────────────────

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->query($key, $default);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->post($key, $default);
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->json($key, $default);
    }

    // ── Headers & Auth ───────────────────────────────────────────────────────

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->header($key, $default);
    }

    public function bearerToken(): ?string
    {
        return $this->httpRequestContract->bearerToken();
    }

    // ── Files ─────────────────────────────────────────────────────────────────

    public function file(string $key): mixed
    {
        return $this->httpRequestContract->file($key);
    }

    public function hasFile(string $key): bool
    {
        return $this->httpRequestContract->hasFile($key);
    }

    // ── Cookies & Route ──────────────────────────────────────────────────────

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->cookie($key, $default);
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->httpRequestContract->route($key, $default);
    }

    // ── Method & URL ─────────────────────────────────────────────────────────

    public function method(): string
    {
        return $this->httpRequestContract->method();
    }

    public function isMethod(string $method): bool
    {
        return $this->httpRequestContract->isMethod($method);
    }

    public function url(): string
    {
        return $this->httpRequestContract->url();
    }

    public function fullUrl(): string
    {
        return $this->httpRequestContract->fullUrl();
    }

    public function path(): string
    {
        return $this->httpRequestContract->path();
    }

    // ── Client & Content-Type ────────────────────────────────────────────────

    public function ip(): ?string
    {
        return $this->httpRequestContract->ip();
    }

    public function isJson(): bool
    {
        return $this->httpRequestContract->isJson();
    }

    public function wantsJson(): bool
    {
        return $this->httpRequestContract->wantsJson();
    }

    public function isAjax(): bool
    {
        return $this->httpRequestContract->isAjax();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function user(): mixed
    {
        return $this->httpRequestContract->user();
    }
}
