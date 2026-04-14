<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Compiler\Behavior;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Http\Symfony\DependencyInjection\SymfonyRegistrar;

final class RoutingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        SymfonyRegistrar::registerRouting($container);
    }
}
