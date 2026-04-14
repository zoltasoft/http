<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Http\Service\Contracts\HTTP;
use Zolta\Http\Service\Laravel\Services\LaravelHTTP;

class HTTPServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HTTP::class, LaravelHTTP::class);
    }
}
