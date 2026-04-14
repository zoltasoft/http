<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Services\Locator;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Builds a service locator for CQRS command/query handlers so they stay private.
 */
final class HandlerLocatorBuilder
{
    /**
     * @param  array<class-string, array{handler?: string|object, validator?: string|object}>  $commandMap
     * @param  array<class-string, array{handler?: string|object}>  $queryMap
     * @param  iterable<class-string, object>  $taggedCommandHandlers
     * @param  iterable<class-string, object>  $taggedQueryHandlers
     * @return ServiceLocator<object>
     */
    public static function build(
        ContainerInterface $container,
        array $commandMap,
        array $queryMap,
        iterable $taggedCommandHandlers = [],
        iterable $taggedQueryHandlers = []
    ): ServiceLocator {
        $services = [];

        $taggedCommandHandlers = is_array($taggedCommandHandlers) ? $taggedCommandHandlers : iterator_to_array($taggedCommandHandlers);
        $taggedQueryHandlers = is_array($taggedQueryHandlers) ? $taggedQueryHandlers : iterator_to_array($taggedQueryHandlers);

        foreach ($commandMap as $meta) {
            if (! empty($meta['handler']) && is_string($meta['handler'])) {
                $id = $meta['handler'];
                $services[$id] = (static fn (): object => $taggedCommandHandlers[$id] ?? $container->get($id));
            }
            if (! empty($meta['validator']) && is_string($meta['validator'])) {
                $id = $meta['validator'];
                $services[$id] = (static fn (): object => $taggedCommandHandlers[$id] ?? $container->get($id));
            }
        }

        foreach ($queryMap as $meta) {
            if (! empty($meta['handler']) && is_string($meta['handler'])) {
                $id = $meta['handler'];
                $services[$id] = (static fn (): object => $taggedQueryHandlers[$id] ?? $container->get($id));
            }
        }

        return new ServiceLocator($services);
    }
}
