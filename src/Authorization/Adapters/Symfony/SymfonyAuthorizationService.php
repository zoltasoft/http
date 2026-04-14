<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Symfony;

use LogicException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Zolta\Exceptions\Rest\UnauthorizedException;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;

/**
 * Symfony-backed authorization adapter.
 */
final readonly class SymfonyAuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(
        private mixed $authorizationChecker,
        private mixed $tokenStorage = null
    ) {}

    public function ensureAuthorized(string $ability, mixed $subject = null): void
    {
        $user = null;
        try {
            if ($this->tokenStorage && method_exists($this->tokenStorage, 'getToken')) {
                $token = $this->tokenStorage->getToken();
                if ($token && method_exists($token, 'getUser')) {
                    $user = $token->getUser();
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        if (AuthorizationMatrix::isGranted($ability, $user)) {
            return;
        }

        if (! self::isAvailable()) {
            throw new LogicException('Symfony Security is required for SymfonyAuthorizationService');
        }

        $checker = $this->authorizationChecker;

        if (! $checker instanceof AuthorizationCheckerInterface) {
            throw new LogicException('Authorization checker is not available');
        }

        if (! $checker->isGranted($ability, $subject)) {
            throw new UnauthorizedException;
        }
    }

    public static function isAvailable(): bool
    {
        return interface_exists('Symfony\\Component\\Security\\Core\\Authorization\\AuthorizationCheckerInterface');
    }
}
