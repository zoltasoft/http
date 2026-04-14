<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Laravel;

use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Zolta\Http\Authorization\Interfaces\UserInterface;
use Zolta\Http\Authorization\Interfaces\UserResolverInterface;

/**
 * Laravel-specific user resolver implementation.
 * Handles Doctrine lazy loading and Laravel authentication.
 */
final class LaravelUserResolver implements UserResolverInterface
{
    /**
     * Get the current authenticated user from Laravel's auth system.
     * Returns a UserInterface adapter wrapping the Laravel user.
     */
    public function currentUser(): ?UserInterface
    {
        // Laravel helper
        if (function_exists('auth')) {
            try {
                $user = auth()->user();

                if (is_object($user)) {
                    $this->eagerLoadRelationships($user);

                    return new LaravelUserAdapter($user);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }

    /**
     * Eager load relationships that may contain permissions to avoid N+1 queries.
     * This handles Doctrine lazy loading for Laravel applications using Doctrine ORM.
     */
    private function eagerLoadRelationships(object $user): void
    {
        // Handle Doctrine proxies/collections if Doctrine is available
        if (class_exists(Proxy::class) && $user instanceof Proxy) {
            try {
                $user->__load();
            } catch (\Throwable) {
                // ignore
            }
        }

        // Common permission-related attributes that might need eager loading
        $attributesToCheck = ['permissions', 'role', 'roles'];

        foreach ($attributesToCheck as $attributeToCheck) {
            $this->loadAttributeIfExists($user, $attributeToCheck);
        }
    }

    /**
     * Load a specific attribute if it exists and is a Doctrine collection/proxy.
     */
    private function loadAttributeIfExists(object $user, string $attribute): void
    {
        $value = null;

        // Try direct property access
        if (isset($user->{$attribute})) {
            $value = $user->{$attribute};
        } else {
            // Try getter method
            $getter = 'get'.str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $attribute)));
            if (method_exists($user, $getter)) {
                $value = $user->{$getter}();
            }
        }

        // Initialize Doctrine collections/proxies if present
        if (class_exists(PersistentCollection::class) && $value instanceof PersistentCollection) {
            try {
                if (! $value->isInitialized()) {
                    $value->initialize();
                }
            } catch (\Throwable) {
                // ignore
            }
        } elseif (class_exists(Proxy::class) && $value instanceof Proxy) {
            try {
                $value->__load();
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
