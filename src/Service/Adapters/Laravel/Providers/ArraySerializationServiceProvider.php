<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Domain\Interfaces\Serializer;
use Zolta\Http\Service\Laravel\Services\ToArraySerializer;

class ArraySerializationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Serializer::class, ToArraySerializer::class);
        // $this->app->bind(ResponseBridge::class, LaravelResponseBridge::class);
    }
}
