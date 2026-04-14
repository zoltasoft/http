<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Invocation;

use Illuminate\Http\Request;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadata;
use Zolta\Http\Router\Laravel\Bootstrap\Request\RequestDtoFactory;

/**
 * Invokes controller methods discovered via attributes, injecting Request and
 * optional DTO arguments.
 */
final class ControllerInvoker
{
    public function invoke(
        RouteMetadata $routeMetadata,
        Request $request,
        RequestDtoFactory $requestDtoFactory
    ): mixed {
        $controller = app($routeMetadata->controllerClass);

        if ($routeMetadata->hasRequest()) {
            $dto = $requestDtoFactory->create($request, $routeMetadata);

            return $controller->{$routeMetadata->method}($request, $dto);
        }

        return app()->call([$controller, $routeMetadata->method], [
            Request::class => $request,
        ]);
    }
}
