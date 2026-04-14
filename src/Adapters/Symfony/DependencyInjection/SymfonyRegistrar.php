<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Framework\FrameworkRegistry;
use Zolta\Http\Symfony\DependencyInjection\Registrar\Registry;
use Zolta\Http\Symfony\DependencyInjection\Support\ContextFactory;
use Zolta\Http\Symfony\SymfonyHttpAdapter;

final readonly class SymfonyRegistrar
{
    private Registry $registry;

    public function __construct(?Registry $registry = null)
    {
        $this->registry = $registry ?? Registry::default();
    }

    /**
     * Aggregate registration for all Symfony integrations.
     */
    public static function register(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerAll($containerBuilder);
    }

    /**
     * Compiler-pass entrypoint: bind framework abstractions.
     */
    public static function registerFrameworkBindings(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerFrameworkBindingsOnly($containerBuilder);
    }

    /**
     * Compiler-pass entrypoint: register routes/attribute loader.
     */
    public static function registerRouting(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerRoutingOnly($containerBuilder);
    }

    /**
     * Compiler-pass entrypoint: register exception handling.
     */
    public static function registerExceptionHandling(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerExceptionHandlingOnly($containerBuilder);
    }

    /**
     * Compiler-pass entrypoint: console commands.
     */
    public static function registerConsoleCommands(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerConsoleCommandsOnly($containerBuilder);
    }

    /**
     * Compiler-pass entrypoint: autoconfiguration.
     */
    public static function registerAutoconfiguration(ContainerBuilder $containerBuilder, ?Registry $registry = null): void
    {
        (new self($registry))->registerAutoconfigurationOnly($containerBuilder);
    }

    public function registerAll(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $cqrsContext = ContextFactory::build($containerBuilder);

        $this->registry->autoconfiguration()->register($containerBuilder);
        $this->registry->frameworkBindings()->register($containerBuilder);
        $this->registry->routing()->register($containerBuilder, $cqrsContext);
        $this->registry->exception()->register($containerBuilder);
        $this->registry->console()->register($containerBuilder, $cqrsContext);
    }

    public function registerFrameworkBindingsOnly(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $this->registry->autoconfiguration()->register($containerBuilder);
        $this->registry->frameworkBindings()->register($containerBuilder);
    }

    public function registerRoutingOnly(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $this->registry->routing()->register($containerBuilder, ContextFactory::build($containerBuilder));
    }

    public function registerExceptionHandlingOnly(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $this->registry->exception()->register($containerBuilder);
    }

    public function registerConsoleCommandsOnly(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $this->registry->console()->register($containerBuilder, ContextFactory::build($containerBuilder));
    }

    public function registerAutoconfigurationOnly(ContainerBuilder $containerBuilder): void
    {
        $this->registry->autoconfiguration()->register($containerBuilder);
    }

    public function registerCqrsMapsAndLocatorsOnly(ContainerBuilder $containerBuilder): void
    {
        if (! $this->shouldProcess($containerBuilder)) {
            return;
        }

        $cqrsContext = ContextFactory::build($containerBuilder);
        $this->registry->cqrs()->register($containerBuilder);
    }

    private function shouldProcess(ContainerBuilder $containerBuilder): bool
    {
        return $this->supportsContainer($containerBuilder)
            && FrameworkRegistry::resolve() === SymfonyHttpAdapter::class;
    }

    private function supportsContainer(mixed $container): bool
    {
        return $container instanceof ContainerBuilder;
    }
}
