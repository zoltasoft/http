<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Metadata;

/**
 * Immutable value object describing the execution metadata for a routed action.
 *
 * @property-read string      $controllerClass Fully-qualified controller class.
 * @property-read string      $method          Method name to invoke.
 * @property-read string|null $requestClass    Optional form request class.
 * @property-read string|null $inputDtoClass   Optional DTO class to hydrate.
 * @property-read string|null $serviceClass    Optional service class for CQRS-style execution.
 * @property-read string|null $resourceClass   Optional resource for response normalization.
 * @property-read int         $status          HTTP status to return on success.
 */
final class RouteMetadata
{
    public function __construct(
        public string $controllerClass,
        public string $method,
        public ?string $requestClass = null,
        public ?string $inputDtoClass = null,
        public ?string $serviceClass = null,
        public ?string $resourceClass = null,
        public int $status = 200,
        public string $message = 'Success.',
    ) {}

    public function hasService(): bool
    {
        return $this->serviceClass !== null;
    }

    public function hasRequest(): bool
    {
        return $this->requestClass !== null;
    }
}
