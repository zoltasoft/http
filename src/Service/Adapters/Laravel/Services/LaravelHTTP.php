<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Services;

use Illuminate\Http\Request;
use Zolta\Http\Service\Contracts\HTTP;

/**
 * Converts the current Laravel request into a
 * framework-agnostic Zolta\Http\Request (aliased to the native request).
 */
final class LaravelHTTP implements HTTP
{
    /**
     * {@inheritDoc}
     *
     * Resolves the current Laravel request automatically.
     */
    public function request(): Request
    {
        return app(Request::class);
    }
}
