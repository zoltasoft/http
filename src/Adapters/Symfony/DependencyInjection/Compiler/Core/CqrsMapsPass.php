<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Compiler\Core;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Http\Symfony\DependencyInjection\Registrar\CqrsMapRegistrar;

final class CqrsMapsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        (new CqrsMapRegistrar)->register($container);
    }
}
