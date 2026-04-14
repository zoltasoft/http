<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;
use Zolta\Framework\FrameworkRegistry;
use Zolta\Http\Response\Contracts\ResponseBridge;

final class RouterInvocker
{
    public function __invoke(Request $request): Response|JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $binding = FrameworkRegistry::resolveBinding(ResponseBridge::class);
        if ($binding === null) {
            throw new LogicException('No HTTP ResponseBridge bound');
        }

        // Case 1: binding is a class-string → instantiate via container
        $bridge = is_string($binding) ? app($binding) : $binding;

        if (! $bridge instanceof ResponseBridge) {
            throw new LogicException(
                sprintf(
                    'Resolved ResponseBridge [%s] does not implement %s',
                    get_debug_type($bridge),
                    ResponseBridge::class
                )
            );
        }

        return $bridge->respond($payload, $status);
    }
}
