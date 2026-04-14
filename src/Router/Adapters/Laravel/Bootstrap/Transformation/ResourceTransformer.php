<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Transformation;

use Illuminate\Http\Request;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadata;

/**
 * Applies an optional Laravel JsonResource (or similar) to the service result.
 */
final class ResourceTransformer
{
    public function transform(RouteMetadata $routeMetadata, mixed $result, Request $request): mixed
    {
        if (! $routeMetadata->resourceClass) {
            return $result;
        }

        $resource = app()->make($routeMetadata->resourceClass, [
            'response' => $result,
        ]);

        return $resource->toArray($request);
    }
}
