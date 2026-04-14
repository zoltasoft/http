<?php

declare(strict_types=1);

namespace Zolta\Http\Response;

use LogicException;
use Zolta\Framework\FrameworkRegistry;
use Zolta\Http\Response\Contracts\ResponseBridge;
use Zolta\Support\ContainerRegistry;

final class HttpResponse
{
    public static function fromPayload(
        ResponsePayload $responsePayload,
        int $status = 200
    ): mixed {
        $binding = FrameworkRegistry::resolveBinding(ResponseBridge::class);
        if ($binding === null) {
            throw new LogicException('No HTTP ResponseBridge bound');
        }

        // Case 1: binding is a class-string → instantiate via container
        $bridge = is_string($binding) ? self::resolveClassBinding($binding) : $binding;

        if (! $bridge instanceof ResponseBridge) {
            throw new LogicException(
                sprintf(
                    'Resolved ResponseBridge [%s] does not implement %s',
                    get_debug_type($bridge),
                    ResponseBridge::class
                )
            );
        }

        return $bridge->respond($responsePayload, $status);
    }

    /**
     * Resolve a class-string binding using the active framework container, falling back gracefully.
     */
    private static function resolveClassBinding(string $binding): object
    {
        // Preferred: PSR-11 container registered by the active framework (Symfony bundle sets this).
        try {
            $container = ContainerRegistry::get();
            if ($container->has($binding)) {
                return $container->get($binding);
            }
        } catch (\Throwable) {
            // ignore and try other strategies
        }

        // Laravel helper if available
        if (function_exists('app')) {
            return app($binding);
        }

        // Last resort: instantiate directly ONLY when there are no required ctor args
        $reflectionClass = new \ReflectionClass($binding);
        $ctor = $reflectionClass->getConstructor();
        if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
            return $reflectionClass->newInstance();
        }

        throw new LogicException(sprintf(
            'Unable to resolve [%s]: no container bound and constructor requires dependencies.',
            $binding
        ));
    }

    /**
     * Convenience method for directly returning a response.
     */
    public static function send(
        bool $success,
        string $message = '',
        array $data = [],
        array $errors = [],
        array $debug = [],
        int $status = 200
    ): mixed {
        $responsePayload = new ResponsePayload(
            success: $success,
            message: $message,
            data: $data,
            errors: $errors,
            debug: $debug
        );

        return self::fromPayload($responsePayload, $status);
    }
}
