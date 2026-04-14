<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zolta\Http\Symfony\Bootstrap\Execution\RouteInvoker;

/**
 * Lightweight proxy to invoke target controllers discovered via attributes.
 */
final readonly class SymfonyProxyController
{
    public function __construct(private RouteInvoker $routeInvoker) {}

    public function __invoke(Request $request): Response
    {
        return $this->routeInvoker->execute($request);
    }
}
