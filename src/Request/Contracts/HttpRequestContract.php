<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Contracts;

interface HttpRequestContract
{
    // ── Input ────────────────────────────────────────────────────────────────

    /** Return all input data (body + query). */
    public function all(): array;

    /** Get a single input value, with optional default. */
    public function input(string $key, mixed $default = null): mixed;

    /** Return only the listed keys from input. */
    public function only(array $keys): array;

    /** Return all input except the listed keys. */
    public function except(array $keys): array;

    /** True if all given keys are present in input. */
    public function has(string|array $key): bool;

    /** True if all given keys are present and non-empty. */
    public function filled(string|array $key): bool;

    // ── Query / Body ─────────────────────────────────────────────────────────

    /** Get a value from the query string. */
    public function query(string $key, mixed $default = null): mixed;

    /** Get a value from the POST body. */
    public function post(string $key, mixed $default = null): mixed;

    /** Get a value from the JSON body. */
    public function json(string $key, mixed $default = null): mixed;

    // ── Headers & Auth ───────────────────────────────────────────────────────

    /** Get a request header value. */
    public function header(string $key, mixed $default = null): mixed;

    /** Extract the Bearer token from the Authorization header. */
    public function bearerToken(): ?string;

    // ── Files ─────────────────────────────────────────────────────────────────

    /** Get an uploaded file by input name. */
    public function file(string $key): mixed;

    /** True if an uploaded file exists for the given key. */
    public function hasFile(string $key): bool;

    // ── Cookies & Route ──────────────────────────────────────────────────────

    /** Get a cookie value. */
    public function cookie(string $key, mixed $default = null): mixed;

    /** Get a route/path parameter. */
    public function route(string $key, mixed $default = null): mixed;

    // ── Method & URL ─────────────────────────────────────────────────────────

    /** Return the HTTP verb (uppercase). */
    public function method(): string;

    /** True if the request uses the given HTTP verb. */
    public function isMethod(string $method): bool;

    /** URL without the query string. */
    public function url(): string;

    /** Full URL including query string. */
    public function fullUrl(): string;

    /** Request path (no host, no query string). */
    public function path(): string;

    // ── Client & Content-Type ────────────────────────────────────────────────

    /** Client IP address. */
    public function ip(): ?string;

    /** True if the request Content-Type is JSON. */
    public function isJson(): bool;

    /** True if the client expects a JSON response (Accept header). */
    public function wantsJson(): bool;

    /** True if the request was made via XMLHttpRequest. */
    public function isAjax(): bool;

    // ── Auth ──────────────────────────────────────────────────────────────────

    /** Return the authenticated user (if any). */
    public function user(): mixed;
}
