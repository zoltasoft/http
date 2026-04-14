<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Interfaces;

/**
 * Framework-agnostic interface for resolving the current user.
 * Implementations should be provided by framework adapters in the Infrastructure layer.
 * Resolved users must implement UserInterface for type safety and consistency.
 */
interface UserResolverInterface
{
    /**
     * Get the current authenticated user, or null if not authenticated.
     * The returned user object must implement UserInterface.
     */
    public function currentUser(): ?UserInterface;
}
