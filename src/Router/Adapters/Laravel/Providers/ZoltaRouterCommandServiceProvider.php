<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Http\Router\Laravel\Console\Commands\Cache\CacheAttributeRoutesCommand;
use Zolta\Http\Router\Laravel\Console\Commands\Cache\ClearAttributeRoutesCommand;
use Zolta\Http\Router\Laravel\Console\Commands\WatchRoutesCommand;

/**
 * Registers artisan commands related to attribute-based routing.
 */
class ZoltaRouterCommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheAttributeRoutesCommand::class,
                ClearAttributeRoutesCommand::class,
                WatchRoutesCommand::class,
            ]);
        }
    }
}
