<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Psr\Container\ContainerInterface;
use ReflectionMethod;
use Zolta\Http\Request\BaseRequest;
use Zolta\Http\Symfony\Services\CqrsLocator;

final readonly class ControllerCaller
{
    public function __construct(
        private CqrsLocator $cqrsLocator,
        private ContainerInterface $container  // Add container for request objects
    ) {}

    public function call(string $controller, string $method): mixed
    {
        $controllerInstance = new $controller;
        $reflectionMethod = new ReflectionMethod($controller, $method);
        $parameters = $reflectionMethod->getParameters();
        $arguments = [];

        foreach ($parameters as $parameter) {
            $arguments[] = $this->resolveParameter($parameter);
        }

        return $reflectionMethod->invokeArgs($controllerInstance, $arguments);
    }

    private function resolveParameter(\ReflectionParameter $reflectionParameter): mixed
    {
        $type = $reflectionParameter->getType();

        if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();

            // Check if it's a BaseRequest or derived from BaseRequest
            if ($this->isBaseRequestOrSubclass($typeName)) {
                // Try to get from container first
                if ($this->container->has($typeName)) {
                    return $this->container->get($typeName);
                }

                // If not in container, try CqrsLocator
                if ($this->cqrsLocator->has($typeName)) {
                    return $this->cqrsLocator->get($typeName);
                }

                // Request not found in either container
                throw new \RuntimeException(
                    sprintf(
                        'Request object "%s" is not available. '.
                        'Make sure it\'s registered in the container.',
                        $typeName
                    )
                );
            }

            // Try to resolve services/dependencies from CqrsLocator
            if ($this->cqrsLocator->has($typeName)) {
                return $this->cqrsLocator->get($typeName);
            }

            // Service not found in CqrsLocator
            throw new \RuntimeException(
                sprintf(
                    'Service "%s" is not available via CqrsLocator. '.
                    'Make sure it\'s tagged with "zolta.cqrs.app_service" in services.yaml.',
                    $typeName
                )
            );
        }

        // Handle defaults
        if ($reflectionParameter->isDefaultValueAvailable()) {
            return $reflectionParameter->getDefaultValue();
        }

        if ($reflectionParameter->allowsNull()) {
            return null;
        }

        throw new \RuntimeException(
            sprintf('Cannot resolve parameter "$%s"', $reflectionParameter->getName())
        );
    }

    private function isBaseRequestOrSubclass(string $className): bool
    {
        // Use is_subclass_of which properly checks the class hierarchy
        return is_subclass_of($className, BaseRequest::class) || $className === BaseRequest::class;
    }
}
