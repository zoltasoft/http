<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Http\Router\Laravel\Bootstrap\AttributeRouteCache;

/**
 * Boots attribute route caching for Laravel apps when enabled in configuration.
 */
class ZoltaRouterAttributeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only run if explicitly enabled in config or .env
        if (! config('zolta-http.routes.cache.enabled')) {
            return;
        }

        if (! config('zolta-http.routes.cache.ensure_fresh_on_boot', false)) {
            return;
        }

        $this->app->booted(static function (): void {
            (new AttributeRouteCache)->ensureLoaded();
        });
    }
}
