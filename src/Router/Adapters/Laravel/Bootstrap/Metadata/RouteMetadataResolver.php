<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap\Metadata;

use Zolta\Http\Request\Attributes\Request as RequestAttr;
use Zolta\Http\Response\Attributes\Response as ResponseAttr;
use Zolta\Http\Router\Cache\ReflectionCache;
use Zolta\Http\Service\Attributes\Service as ServiceAttr;

/**
 * Resolves effective Request/Service/Response attributes by combining
 * class-level defaults with method-level overrides.
 */
final class RouteMetadataResolver
{
    public function resolve(string $controllerClass, string $method): RouteMetadata
    {
        $classAttrs = ReflectionCache::getClassAttributes($controllerClass);
        $methodAttrs = ReflectionCache::getMethodAttributes($controllerClass, $method);

        $request = $this->findAttr($classAttrs, $methodAttrs, RequestAttr::class);
        $service = $this->findAttr($classAttrs, $methodAttrs, ServiceAttr::class);
        $response = $this->findAttr($classAttrs, $methodAttrs, ResponseAttr::class);

        return new RouteMetadata(
            controllerClass: $controllerClass,
            method: $method,
            requestClass: $request['arguments'][0] ?? null,
            inputDtoClass: $request['arguments']['inputDto'] ?? ($request['arguments'][1] ?? null),
            serviceClass: $service['arguments'][0] ?? null,
            resourceClass: $response['arguments'][0] ?? null,
            status: $service['arguments'][2] ?? 200,
            message: $service['arguments'][1] ?? 'Success.',
        );
    }

    private function findAttr(array $classAttrs, array $methodAttrs, string $target): ?array
    {
        foreach ($methodAttrs as $methodAttr) {
            if ($methodAttr['class'] === $target) {
                return $methodAttr;
            }
        }

        foreach ($classAttrs as $classAttr) {
            if ($classAttr['class'] === $target) {
                return $classAttr;
            }
        }

        return null;
    }
}
