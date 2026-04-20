<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Contracts;

use Zolta\Http\Authorization\Interfaces\UserInterface;
use Zolta\Http\Authorization\Interfaces\UserResolverInterface;

/**
 * Authorization Matrix Interface
 *
 * Framework-agnostic contract for checking user permissions and abilities.
 * Provides centralized authorization logic that works across different frameworks.
 */
interface AuthorizationMatrixInterface
{
    /**
     * Check if the given abilities/permissions are granted for the current user.
     *
     * @param  string|array  $abilitiesOrPermissions  Ability names or permission strings
     * @param  UserInterface|null  $user  Specific user to check (defaults to current user)
     * @return bool True if authorized, false otherwise
     */
    public function isGranted(string|array $abilitiesOrPermissions, ?UserInterface $user = null): bool;

    /**
     * Expand abilities into their concrete permissions.
     *
     * @param  string|array  $abilitiesOrPermissions  Abilities to expand
     * @return array Concrete permission strings
     */
    public function requiredPermissions(string|array $abilitiesOrPermissions): array;

    /**
     * Extract permissions from a user object.
     *
     * @param  object|null  $user  User to extract permissions from (UserInterface, framework model, or any object)
     * @return array Permission strings
     */
    public function permissionsForUser(?object $user): array;

    /**
     * Set the user resolver for finding the current user.
     *
     * @param  UserResolverInterface  $userResolver  User resolver implementation
     */
    public function setUserResolver(UserResolverInterface $userResolver): void;

    /**
     * Configure abilities (permission mappings).
     *
     * @param  array  $abilities  Map of ability names to permission arrays
     */
    public function setAbilities(array $abilities): void;

    /**
     * Configure user permission attribute paths.
     *
     * @param  array  $attributes  Attribute paths to search for permissions
     */
    public function setUserAttributes(array $attributes): void;
}
