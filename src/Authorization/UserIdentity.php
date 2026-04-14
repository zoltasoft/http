<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization;

use Zolta\Http\Authorization\Interfaces\UserIdentityInterface;
use Zolta\Support\ContainerRegistry;

/**
 * Accessor for the current user identity, delegating to framework adapters.
 */
final class UserIdentity
{
    public static function current(): ?UserIdentityInterface
    {
        // Check DI container first (framework can bind per-request identity).
        try {
            $container = ContainerRegistry::get();
            if ($container->has(UserIdentityInterface::class)) {
                $identity = $container->get(UserIdentityInterface::class);
                if ($identity instanceof UserIdentityInterface) {
                    return $identity;
                }
            }
        } catch (\Throwable) {
            // ignore and fall through
        }

        // Laravel helper
        if (function_exists('auth')) {
            try {
                $guards = [];
                if (function_exists('config')) {
                    $defaultGuard = (string) (config('auth.defaults.guard') ?? '');
                    if ($defaultGuard !== '') {
                        $guards[] = $defaultGuard;
                    }
                    $guards = array_merge($guards, array_keys((array) config('auth.guards', [])));
                }
                if ($guards === []) {
                    $guards[] = null; // fallback to default guard
                }

                foreach ($guards as $guardName) {
                    $guard = $guardName ? auth()->guard($guardName) : auth();
                    if (! $guard || ! method_exists($guard, 'user')) {
                        continue;
                    }
                    $user = $guard->user();
                    if (is_object($user)) {
                        return Identity::fromUser($user);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        // Symfony helper via container
        try {
            $container = ContainerRegistry::get();
            if ($container->has('security.helper')) {
                $helper = $container->get('security.helper');
                if (is_object($helper) && method_exists($helper, 'getUser')) {
                    $user = $helper->getUser();
                    if (is_object($user)) {
                        return Identity::fromUser($user);
                    }
                }
            }
            // Fallback to token storage if helper not present
            if ($container->has('security.token_storage')) {
                $tokenStorage = $container->get('security.token_storage');
                if (is_object($tokenStorage) && method_exists($tokenStorage, 'getToken')) {
                    $token = $tokenStorage->getToken();
                    if ($token && method_exists($token, 'getUser')) {
                        $user = $token->getUser();
                        if (is_object($user)) {
                            return Identity::fromUser($user);
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }
}
