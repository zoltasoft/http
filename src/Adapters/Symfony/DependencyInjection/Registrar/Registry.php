<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

/**
 * Holds registrar instances and allows overrides while preserving defaults.
 */
final readonly class Registry
{
    public function __construct(private ?AutoconfigurationRegistrar $autoconfigurationRegistrar = new AutoconfigurationRegistrar, private ?FrameworkBindingsRegistrar $frameworkBindingsRegistrar = new FrameworkBindingsRegistrar, private ?CqrsMapRegistrar $cqrsMapRegistrar = new CqrsMapRegistrar, private ?RoutingRegistrar $routingRegistrar = new RoutingRegistrar, private ?ExceptionRegistrar $exceptionRegistrar = new ExceptionRegistrar, private ?ConsoleRegistrar $consoleRegistrar = new ConsoleRegistrar) {}

    public function autoconfiguration(): AutoconfigurationRegistrar
    {
        return $this->autoconfigurationRegistrar;
    }

    public function frameworkBindings(): FrameworkBindingsRegistrar
    {
        return $this->frameworkBindingsRegistrar;
    }

    public function cqrs(): CqrsMapRegistrar
    {
        return $this->cqrsMapRegistrar;
    }

    public function routing(): RoutingRegistrar
    {
        return $this->routingRegistrar;
    }

    public function exception(): ExceptionRegistrar
    {
        return $this->exceptionRegistrar;
    }

    public function console(): ConsoleRegistrar
    {
        return $this->consoleRegistrar;
    }

    public static function default(): self
    {
        return new self;
    }
}
