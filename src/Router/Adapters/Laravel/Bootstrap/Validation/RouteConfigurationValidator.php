<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Validation;

use Zolta\Http\Exceptions\ControllerConfigurationException;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadata;

/**
 * Guards against invalid controller/service combinations detected at runtime
 * for attribute-routed endpoints.
 */
final class RouteConfigurationValidator
{
    public function validate(string $controller, string $method, RouteMetadata $routeMetadata): void
    {
        if (! $routeMetadata->hasService() && ! method_exists($controller, $method)) {
            throw new ControllerConfigurationException(
                previous: null,
                errorCode: 'controller.configuration.invalid_handler',
                context: [
                    'controller' => $controller,
                    'method' => $method,
                ]
            );
        }
    }
}
