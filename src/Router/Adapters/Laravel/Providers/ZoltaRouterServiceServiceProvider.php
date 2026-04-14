<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Providers;

use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\ServiceProvider;
use Zolta\Domain\Interfaces\Collection;
use Zolta\Http\Router\Contracts\RouterProvider as RouterProviderContract;
use Zolta\Http\Router\Laravel\Utilities\RouteUtility;
use Zolta\Http\Router\Services\RouterService;

/**
 * Registers router services and framework bindings for Laravel implementations.
 */
class ZoltaRouterServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Collection::class, IlluminateCollection::class);
        $this->app->singleton(RouterProviderContract::class, fn (): RouterProviderContract => new RouteUtility);
        $this->app->singleton(RouterService::class, fn ($app): RouterService => new RouterService($app->make(RouterProviderContract::class)));
    }
}
