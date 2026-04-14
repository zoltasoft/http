<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zolta\Http\Authorization\Symfony\AuthenticationGuard;
use Zolta\Http\Symfony\Bootstrap\Execution\AttributeResolver;
use Zolta\Http\Symfony\Bootstrap\Execution\ConfigurationValidator;
use Zolta\Http\Symfony\Bootstrap\Execution\ControllerCaller;
use Zolta\Http\Symfony\Bootstrap\Execution\CqrsExecutor;
use Zolta\Http\Symfony\Bootstrap\Execution\RequestDtoFactory;
use Zolta\Http\Symfony\Bootstrap\Execution\ResponseFactory;
use Zolta\Http\Symfony\Bootstrap\Execution\RouteInvoker;
use Zolta\Http\Symfony\Services\CqrsLocator;

final class RouteInvokerRegistrar
{
    public function register(ContainerBuilder $containerBuilder): void
    {
        // Register ControllerCaller with CqrsLocator
        $containerBuilder->register('zolta.controller_caller', ControllerCaller::class)
            ->addArgument(new Reference(CqrsLocator::class))  // ← Use CqrsLocator
            ->addArgument(new Reference('service_container'))
            ->setPublic(false);

        $containerBuilder->setAlias(ControllerCaller::class, 'zolta.controller_caller')
            ->setPublic(false);

        // Register RouteInvoker
        $containerBuilder->register('zolta.route_invoker', RouteInvoker::class)
            ->addArgument(new Reference(AuthenticationGuard::class))
            ->addArgument(new Reference(AttributeResolver::class))
            ->addArgument(new Reference(ConfigurationValidator::class))
            ->addArgument(new Reference(RequestDtoFactory::class))
            ->addArgument(new Reference(ControllerCaller::class))
            ->addArgument(new Reference(CqrsExecutor::class))
            ->addArgument(new Reference(ResponseFactory::class))
            ->setPublic(false);

        $containerBuilder->setAlias(RouteInvoker::class, 'zolta.route_invoker')
            ->setPublic(false);
    }
}
