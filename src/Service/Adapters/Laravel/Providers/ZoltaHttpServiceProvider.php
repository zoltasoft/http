<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Http\Authorization\AuthorizationMatrix;

class ZoltaHttpServiceProvider extends ServiceProvider
{
    /**
     * Register all HTTP service providers in order.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zolta-http.php', 'zolta-http');
        $this->mergeConfigFrom(
            dirname(__DIR__, 4).'/Authorization/Adapters/Laravel/config/zolta-security.php',
            'zolta-security',
        );

        // Core bindings
        $this->app->register(LaravelBridgeServiceProvider::class);
        $this->app->register(HTTPServiceProvider::class);
        $this->app->register(ArraySerializationServiceProvider::class);
        $this->app->register(ExceptionServiceProvider::class);

        // Artisan commands
        $this->app->register(ZoltaCommandServiceProvider::class);
    }

    /**
     * Boot: configure authorization matrix and publish configs.
     */
    public function boot(): void
    {
        $securityConfig = config('zolta-security', []);
        if (is_array($securityConfig) && $securityConfig !== []) {
            AuthorizationMatrix::configure($securityConfig);
        }

        $this->publishes([
            __DIR__.'/../config/zolta-http.php' => config_path('zolta-http.php'),
        ], 'zolta-http-config');

        $this->publishes([
            dirname(__DIR__, 4).'/Authorization/Adapters/Laravel/config/zolta-security.php' => config_path('zolta-security.php'),
        ], 'zolta-security-config');
    }
}
