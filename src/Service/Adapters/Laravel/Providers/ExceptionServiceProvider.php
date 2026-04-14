<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Providers;

use Illuminate\Contracts\Debug\ExceptionHandler as ContractExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Zolta\Http\Exceptions\Laravel\ExceptionHandler as ApiExceptionHandler;
use Zolta\Http\Exceptions\Laravel\ExceptionMapper;

class ExceptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // $this->app->singleton(ExceptionMapper::class, function ($app): ExceptionMapper {
        //     /** @var LoggerInterface $logger */
        //     $logger = $app->make(LoggerInterface::class);

        //     return new ExceptionMapper($logger);
        // });

        // $this->app->singleton(ContractExceptionHandler::class, fn ($app): ApiExceptionHandler => new ApiExceptionHandler($app->make(ExceptionMapper::class)));
    }
}
