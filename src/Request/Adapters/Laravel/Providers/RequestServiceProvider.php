<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Providers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\ServiceProvider;
use Zolta\Http\Request\Contracts\RequestPort;
use Zolta\Http\Request\Interfaces\ValidatorInterface;
use Zolta\Http\Request\Laravel\Ports\LaravelRequestPort;
use Zolta\Http\Request\Laravel\Validation\LaravelValidatorAdapter;

final class RequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RequestPort::class, fn ($app): LaravelRequestPort => new LaravelRequestPort($app->make(IlluminateRequest::class)));

        $this->app->bind(ValidatorInterface::class, fn ($app): LaravelValidatorAdapter => new LaravelValidatorAdapter($app->make(ValidationFactory::class)));
    }
}
