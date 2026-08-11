<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Zolta\Http\Identity\Laravel\Exceptions\IdentityServiceUnavailable;
use Zolta\Http\Identity\Laravel\IdentityIntrospector;
use Zolta\Http\Identity\Laravel\IdentityPrincipal;

final readonly class IntrospectIdentity
{
    public function __construct(private IdentityIntrospector $identityIntrospector) {}

    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'An Identity access token is required.'], 401);
        }

        try {
            $identity = $this->identityIntrospector->introspect($token);
        } catch (IdentityServiceUnavailable) {
            return response()->json(['message' => 'The Identity service is unavailable.'], 503);
        }

        if (! $identity) {
            return response()->json(['message' => 'The access token is inactive.'], 401);
        }
        if ($requiredPermission !== null && ! $identity->can($requiredPermission)) {
            return response()->json(['message' => 'The required permission is missing.'], 403);
        }

        $identityPrincipal = new IdentityPrincipal($identity);
        $request->setUserResolver(static fn (): IdentityPrincipal => $identityPrincipal);
        $request->attributes->set('identity', $identity);
        Auth::setUser($identityPrincipal);

        return $next($request);
    }
}
