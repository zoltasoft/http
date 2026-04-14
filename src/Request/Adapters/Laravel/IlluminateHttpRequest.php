<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel;

use Zolta\Http\Request\Contracts\HttpRequestContract;

final class IlluminateHttpRequest implements HttpRequestContract
{
    // ── Input ────────────────────────────────────────────────────────────────

    public function all(): array
    {
        return request()->all();
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return request()->input($key, $default);
    }

    public function only(array $keys): array
    {
        return request()->only($keys);
    }

    public function except(array $keys): array
    {
        return request()->except($keys);
    }

    public function has(string|array $key): bool
    {
        return request()->has($key);
    }

    public function filled(string|array $key): bool
    {
        return request()->filled($key);
    }

    // ── Query / Body ─────────────────────────────────────────────────────────

    public function query(string $key, mixed $default = null): mixed
    {
        return request()->query($key, $default);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return request()->post($key, $default);
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return request()->json($key, $default);
    }

    // ── Headers & Auth ───────────────────────────────────────────────────────

    public function header(string $key, mixed $default = null): mixed
    {
        return request()->header($key, $default);
    }

    public function bearerToken(): ?string
    {
        return request()->bearerToken();
    }

    // ── Files ─────────────────────────────────────────────────────────────────

    public function file(string $key): mixed
    {
        return request()->file($key);
    }

    public function hasFile(string $key): bool
    {
        return request()->hasFile($key);
    }

    // ── Cookies & Route ──────────────────────────────────────────────────────

    public function cookie(string $key, mixed $default = null): mixed
    {
        return request()->cookie($key, $default);
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return request()->route($key) ?? $default;
    }

    // ── Method & URL ─────────────────────────────────────────────────────────

    public function method(): string
    {
        return request()->method();
    }

    public function isMethod(string $method): bool
    {
        return request()->isMethod($method);
    }

    public function url(): string
    {
        return request()->url();
    }

    public function fullUrl(): string
    {
        return request()->fullUrl();
    }

    public function path(): string
    {
        return request()->path();
    }

    // ── Client & Content-Type ────────────────────────────────────────────────

    public function ip(): ?string
    {
        return request()->ip();
    }

    public function isJson(): bool
    {
        return request()->isJson();
    }

    public function wantsJson(): bool
    {
        return request()->wantsJson();
    }

    public function isAjax(): bool
    {
        return request()->ajax();
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function user(): mixed
    {
        return request()->user();
    }
}
