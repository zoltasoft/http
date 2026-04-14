<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Http\Symfony\DependencyInjection\Support\CqrsContext;

final class RoutingRegistrar
{
    public function register(ContainerBuilder $containerBuilder, CqrsContext $cqrsContext): void
    {
        (new ExecutionPipelineRegistrar)->register($containerBuilder);
        (new RouteInvokerRegistrar)->register($containerBuilder);
        (new SymfonyRoutingRegistrar)->register($containerBuilder, $cqrsContext);
    }
}
