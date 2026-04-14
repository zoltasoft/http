<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zolta\Http\Symfony\Bootstrap\Execution\RouteInvoker;
use Zolta\Http\Symfony\Bootstrap\Routing\SymfonyProxyController;
use Zolta\Http\Symfony\Bootstrap\Routing\SymfonyRouteLoader;
use Zolta\Http\Symfony\DependencyInjection\Support\CqrsContext;

final class SymfonyRoutingRegistrar
{
    public function register(ContainerBuilder $containerBuilder, CqrsContext $cqrsContext): void
    {
        $containerBuilder->register('zolta.route_loader', SymfonyRouteLoader::class)
            ->addArgument($cqrsContext->projectDir)
            ->addArgument($cqrsContext->routePaths)
            ->addTag('routing.loader')
            ->setPublic(false);

        $containerBuilder->setAlias(SymfonyRouteLoader::class, 'zolta.route_loader')
            ->setPublic(false);

        $containerBuilder->register('zolta.proxy_controller', SymfonyProxyController::class)
            ->addArgument(new Reference(RouteInvoker::class))
            ->setPublic(true);

        $containerBuilder->setAlias(SymfonyProxyController::class, 'zolta.proxy_controller')
            ->setPublic(true);
    }
}
