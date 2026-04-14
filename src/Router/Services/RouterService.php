<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Services;

use Zolta\Http\Router\Contracts\RouterProvider;

final readonly class RouterService
{
    public function __construct(private RouterProvider $routerProvider) {}

    /**
     * Return the current framework router through the configured provider.
     */
    public function router(): mixed
    {
        return $this->routerProvider->router();
    }
}
