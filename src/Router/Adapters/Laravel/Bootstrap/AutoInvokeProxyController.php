<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * AutoInvokeProxyController
 *
 * This proxy acts as a universal entry point for attribute-based controllers.
 */
final class AutoInvokeProxyController
{
    public function __invoke(Request $request): Response|JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        return app(RouteInvoker::class)->execute($request);
    }
}
