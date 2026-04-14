<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Zolta\Http\Symfony\DependencyInjection\Compiler\Adapter\FrameworkBindingsPass;
use Zolta\Http\Symfony\DependencyInjection\Compiler\Behavior\ConsoleCommandsPass;
use Zolta\Http\Symfony\DependencyInjection\Compiler\Behavior\ExceptionHandlingPass;
use Zolta\Http\Symfony\DependencyInjection\Compiler\Behavior\RoutingPass;
use Zolta\Http\Symfony\DependencyInjection\Compiler\Core\CqrsMapsPass;
use Zolta\Support\ContainerRegistry;
use Zolta\Support\ZoltaForgeContainer;

// Declare without hard Symfony dependencies to keep package loadable without Symfony.
if (class_exists(Bundle::class)) {
    class ZoltaForgeBundle extends Bundle
    {
        public function build(ContainerBuilder $container): void
        {
            parent::build($container);

            if (! interface_exists(CompilerPassInterface::class)) {
                return;
            }

            // Register compiler passes that split the registrar responsibilities.
            $container->addCompilerPass(new FrameworkBindingsPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 80);
            $container->addCompilerPass(new CqrsMapsPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 75);
            $container->addCompilerPass(new RoutingPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 70);
            $container->addCompilerPass(new ExceptionHandlingPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 30);
            $container->addCompilerPass(new ConsoleCommandsPass, PassConfig::TYPE_BEFORE_OPTIMIZATION, 20);
        }

        public function boot(): void
        {
            parent::boot();

            if ($this->container instanceof ContainerInterface) {
                ContainerRegistry::set(new ZoltaForgeContainer($this->container));
            }
        }
    }
} else {
    class ZoltaForgeBundle {}
}
