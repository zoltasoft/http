<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zolta\Http\Symfony\Exceptions\ExceptionMapper;
use Zolta\Http\Symfony\Exceptions\ExceptionSubscriber;

final class ExceptionRegistrar
{
    public static function register(ContainerBuilder $containerBuilder): void
    {
        $loggerRef = $containerBuilder->has('logger')
            ? new Reference('logger')
            : new Definition(NullLogger::class);

        $containerBuilder->register(ExceptionMapper::class, ExceptionMapper::class)
            ->addArgument($loggerRef);

        $containerBuilder->register(ExceptionSubscriber::class, ExceptionSubscriber::class)
            ->addArgument(new Reference(ExceptionMapper::class))
            ->addTag('kernel.event_subscriber');
    }
}
