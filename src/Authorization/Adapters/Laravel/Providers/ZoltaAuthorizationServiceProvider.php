<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Laravel\Providers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\ServiceProvider;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Authorization\Interfaces\UserResolverInterface;
use Zolta\Http\Authorization\Laravel\LaravelAuthorizationService;
use Zolta\Http\Authorization\Laravel\LaravelUserResolver;

final class ZoltaAuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AuthorizationServiceInterface::class,
            fn ($app): LaravelAuthorizationService => new LaravelAuthorizationService($app->make(Gate::class))
        );

        $this->app->bind(
            UserResolverInterface::class,
            fn ($app): LaravelUserResolver => new LaravelUserResolver
        );
    }

    public function boot(): void
    {
        try {
            // Load the zolta_security config into the matrix so abilities and
            // user attribute paths are resolved before the first check.
            $config = config('zolta-security', []);
            if (is_array($config) && $config !== []) {
                AuthorizationMatrix::configure($config);
            }

            AuthorizationMatrix::setUserResolver($this->app->make(UserResolverInterface::class));
        } catch (\Throwable) {
            // best-effort: if resolver cannot be created, avoid breaking boot
        }
    }
}
