<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Compiler\Behavior;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Http\Symfony\DependencyInjection\SymfonyRegistrar;

final class ConsoleCommandsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        SymfonyRegistrar::registerConsoleCommands($container);
    }
}
