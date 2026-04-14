<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Interfaces;

/**
 * Minimum interface that user objects must implement to work with AuthorizationMatrix.
 * This ensures type safety and consistency across different user implementations.
 */
interface UserInterface
{
    /**
     * Get the unique identifier for this user.
     */
    public function getId(): string|int;

    /**
     * Check if this user is authenticated.
     */
    public function isAuthenticated(): bool;
}
