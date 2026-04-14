<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Providers;

use Illuminate\Support\ServiceProvider;

class ZoltaRequestServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {

        $this->app->register(RequestServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
