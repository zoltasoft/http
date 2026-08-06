<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Zolta\Http\Identity\Laravel\Http\Middleware\IntrospectIdentity;

final class ZoltaIdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/identity-consumer.php', 'identity-consumer');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('identity.introspect', IntrospectIdentity::class);
        $this->publishes([
            __DIR__ . '/../config/identity-consumer.php' => config_path('identity-consumer.php'),
        ], 'identity-consumer-config');
    }
}
