<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Services;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * CQRS service locator that prefers the dedicated service locator map
 * while allowing a fallback to the main container for public services.
 *
 * This lets Symfony apps keep their services private and only expose
 * the CQRS-facing dependencies via a keyed ServiceLocator.
 */
final readonly class CqrsLocator implements ContainerInterface
{
    public function __construct(
        /** @var iterable<ContainerInterface> */
        private iterable $locators,
        private ContainerInterface $container
    ) {}

    public function get(string $id): mixed
    {
        foreach ($this->locators as $locator) {
            if ($locator->has($id)) {
                return $locator->get($id);
            }
        }

        if ($this->container->has($id)) {
            return $this->container->get($id);
        }

        throw new ServiceNotFoundException($id);
    }

    public function has(string $id): bool
    {
        foreach ($this->locators as $locator) {
            if ($locator->has($id)) {
                return true;
            }
        }

        return $this->container->has($id);
    }
}
