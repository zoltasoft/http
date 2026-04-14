<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Request;

use Illuminate\Http\Request;
use Zolta\Http\Request\Laravel\Bridge\LaravelBridgeRequest;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadata;

/**
 * Builds validated DTOs or arrays from Laravel form requests declared via
 * #[Request] attributes.
 */
final class RequestDtoFactory
{
    public function create(Request $request, RouteMetadata $routeMetadata): mixed
    {
        if (! $routeMetadata->hasRequest()) {
            return null;
        }

        $instance = app($routeMetadata->requestClass);

        if ($instance instanceof LaravelBridgeRequest) {
            $instance->merge($request->route()?->parameters() ?? []);
        }

        $result = $instance->toInputDto($routeMetadata->inputDtoClass);

        return is_object($result) && method_exists($result, 'toArray')
            ? $result
            : (array) $result;
    }
}
