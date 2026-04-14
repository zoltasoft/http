<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Zolta\Http\Symfony\Services\CqrsLocator;

final class CqrsMapRegistrar
{
    public function register(ContainerBuilder $containerBuilder): void
    {
        // Create a service locator for all tagged application services
        // This handles #[AsApplicationService] tagged services
        if (! $containerBuilder->hasDefinition('zolta.cqrs.locator.app')) {
            $containerBuilder->register('zolta.cqrs.locator.app', ServiceLocator::class)
                ->addArgument(new TaggedIteratorArgument('zolta.cqrs.app_service', defaultIndexMethod: null, needsIndexes: true))
                ->addTag('container.service_locator')
                ->setPublic(false);
        }

        // Register the main CqrsLocator that can find application services
        $containerBuilder->register(CqrsLocator::class, CqrsLocator::class)
            ->addArgument([
                new Reference('zolta.cqrs.locator.app'),
            ])
            ->addArgument(new Reference('service_container'))
            ->setPublic(true);
    }
}
