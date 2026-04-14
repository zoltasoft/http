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
use Zolta\Http\Symfony\Bootstrap\Execution\ResourceNormalizer;
use Zolta\Http\Symfony\Bootstrap\Execution\ResponseFactory;
use Zolta\Http\Symfony\Bootstrap\Execution\ViewRenderer;
use Zolta\Http\Symfony\Services\CqrsLocator as ServicesCqrsLocator;

final class ExecutionPipelineRegistrar
{
    public function register(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->register(
            AuthenticationGuard::class,
            AuthenticationGuard::class
        )
            ->addArgument(new Reference('service_container'))
            ->setPublic(false);

        $containerBuilder->register(
            AttributeResolver::class,
            AttributeResolver::class
        )->setPublic(false);

        $containerBuilder->register(
            ConfigurationValidator::class,
            ConfigurationValidator::class
        )->setPublic(false);

        $containerBuilder->register(
            RequestDtoFactory::class,
            RequestDtoFactory::class
        )
            ->addArgument(new Reference('service_container'))
            ->setPublic(false);

        $containerBuilder->register(
            ControllerCaller::class,
            ControllerCaller::class
        )->setPublic(false);

        $containerBuilder->register(
            CqrsExecutor::class,
            CqrsExecutor::class
        )
            ->addArgument(new Reference(ServicesCqrsLocator::class))
            ->setPublic(false);

        $containerBuilder->register(ResourceNormalizer::class, ResourceNormalizer::class)
            ->setPublic(false);

        $containerBuilder->register(ViewRenderer::class, ViewRenderer::class)
            ->addArgument(new Reference('service_container'))
            ->setPublic(false);

        $containerBuilder->register(ResponseFactory::class, ResponseFactory::class)
            ->addArgument(new Reference(ResourceNormalizer::class))
            ->addArgument(new Reference(ViewRenderer::class))
            ->setPublic(false);
    }
}
