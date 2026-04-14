<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Zolta\Http\Exceptions\ControllerConfigurationException;
use Zolta\Http\Symfony\Bootstrap\Metadata\AttributeMetadata;

final class ConfigurationValidator
{
    public function validate(
        string $controller,
        string $method,
        AttributeMetadata $attributeMetadata
    ): void {
        if (! $attributeMetadata->requestAttr) {
            return;
        }

        $reflectionMethod = new ReflectionMethod($controller, $method);
        $params = $reflectionMethod->getParameters();

        if ($params === []) {
            return;
        }

        $type = $params[0]->getType();

        if (
            $type instanceof \ReflectionNamedType &&
            ! $type->isBuiltin() &&
            $type->getName() !== Request::class
        ) {
            throw new ControllerConfigurationException(
                errorCode: 'controller.configuration.conflict',
                context: ['controller' => $controller, 'method' => $method]
            );
        }
    }
}
