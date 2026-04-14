<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use ReflectionMethod;
use Zolta\Http\Symfony\Bootstrap\Metadata\AttributeMetadata;

final class AttributeResolver
{
    public function resolve(string $controller, string $method): AttributeMetadata
    {
        $reflectionMethod = new ReflectionMethod($controller, $method);
        $attrs = [];

        foreach ($reflectionMethod->getAttributes() as $attribute) {
            $attrs[] = [
                'class' => $attribute->getName(),
                'args' => $attribute->getArguments(),
            ];
        }

        return AttributeMetadata::from($attrs);
    }
}
