<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Symfony;

use Psr\Container\ContainerInterface;
use Zolta\Http\Authorization\Exceptions\UnauthorizedException;
use Zolta\Http\Exceptions\ControllerConfigurationException;

final readonly class AuthenticationGuard
{
    public function __construct(private ContainerInterface $container) {}

    public function enforce(array $middleware): void
    {
        $needsAuth = array_filter($middleware, fn ($m): bool => is_string($m) && str_starts_with($m, 'auth'));

        if ($needsAuth === []) {
            return;
        }

        if (! $this->container->has('security.authorization_checker')) {
            throw new ControllerConfigurationException(null, 'auth.missing');
        }

        $checker = $this->container->get('security.authorization_checker');

        if (! $checker->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new UnauthorizedException;
        }
    }
}
