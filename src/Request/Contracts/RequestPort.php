<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Contracts;

interface RequestPort
{
    /**
     * Get the full payload (body + merged query + route params).
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /** Read a single value. */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Merge values into the payload (used by traits).
     *
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): void;

    /** Current authenticated actor (can be null). */
    public function user(): mixed;
}
