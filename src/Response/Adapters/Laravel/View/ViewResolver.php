<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Laravel\View;

use Illuminate\Http\Request;
use Zolta\Http\Response\Attributes\Views\View;
use Zolta\Http\Response\Attributes\Views\ViewDefinition;

final class ViewResolver
{
    public function resolve(?Request $request): ?ViewDefinition
    {
        if (! $request instanceof Request) {
            return null;
        }

        $route = $request->route();
        if (! $route) {
            return null;
        }

        // Route default
        if (! empty($route->defaults['_view'])) {
            return new ViewDefinition((string) $route->defaults['_view']);
        }

        try {
            $targetClass = $route->defaults['target'] ?? null;
            $targetMethod = $route->defaults['target_method'] ?? null;

            if (! $targetClass) {
                $actionName = $route->getActionName();
                if (! $actionName) {
                    return null;
                }

                if (str_contains($actionName, '@')) {
                    [$targetClass, $targetMethod] = explode('@', $actionName);
                } else {
                    $targetClass = $actionName;
                    $targetMethod = '__invoke';
                }
            }

            if (! class_exists($targetClass)) {
                return null;
            }

            // Method-level attribute
            if ($targetMethod && method_exists($targetClass, $targetMethod)) {
                $reflectionMethod = new \ReflectionMethod($targetClass, $targetMethod);
                $attrs = $reflectionMethod->getAttributes(View::class);
                if ($attrs !== []) {
                    $instance = $attrs[0]->newInstance();

                    return new ViewDefinition($instance->view, $instance->engine);
                }
            }

            // Class-level attribute
            $reflectionClass = new \ReflectionClass($targetClass);
            $attrs = $reflectionClass->getAttributes(View::class);
            if ($attrs !== []) {
                $instance = $attrs[0]->newInstance();

                return new ViewDefinition($instance->view, $instance->engine);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
