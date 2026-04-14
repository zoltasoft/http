<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Utilities;

use Illuminate\Routing\Router as IlluminateRouter;
use Illuminate\Support\Facades\Route;
use Zolta\Http\Router\Contracts\RouterProvider;

/**
 * Provides the current Laravel router instance for router-agnostic consumers.
 */
final class RouteUtility implements RouterProvider
{
    /**
     * {@inheritDoc}
     */
    public function router(): IlluminateRouter
    {
        if (class_exists(Route::class)) {
            $router = Route::getFacadeRoot();
            if ($router instanceof IlluminateRouter) {
                return $router;
            }
        }

        return app(IlluminateRouter::class);
    }
}
