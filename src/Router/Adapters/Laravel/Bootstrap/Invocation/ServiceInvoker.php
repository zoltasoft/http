<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Invocation;

use Illuminate\Http\Request;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadata;
use Zolta\Http\Router\Laravel\Bootstrap\Request\RequestDtoFactory;

/**
 * Executes a service class resolved from route metadata, handling DTO injection
 * when a Request attribute is present.
 */
final class ServiceInvoker
{
    public function invoke(
        RouteMetadata $routeMetadata,
        Request $request,
        RequestDtoFactory $requestDtoFactory
    ): mixed {
        $service = app($routeMetadata->serviceClass);

        if ($routeMetadata->hasRequest()) {
            return $service($requestDtoFactory->create($request, $routeMetadata));
        }

        return app()->call($service, [
            Request::class => $request,
        ]);
    }
}
