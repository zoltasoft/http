<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Compiler\Adapter;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Http\Symfony\DependencyInjection\SymfonyRegistrar;

final class FrameworkBindingsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        SymfonyRegistrar::registerFrameworkBindings($container);
    }
}
