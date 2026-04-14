<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization;

use Zolta\Http\Authorization\Interfaces\UserInterface;

/**
 * Base adapter class for wrapping framework-specific user objects
 * to implement the UserInterface contract.
 */
abstract class UserAdapter implements UserInterface
{
    public function __construct(protected object $user) {}

    /**
     * Get the underlying framework user object.
     */
    public function getFrameworkUser(): object
    {
        return $this->user;
    }

    /**
     * Get the unique identifier for this user.
     * Subclasses must implement this to extract ID from framework user.
     */
    abstract public function getId(): string|int;

    /**
     * Check if this user is authenticated.
     * Default implementation - subclasses can override if needed.
     */
    public function isAuthenticated(): bool
    {
        return true; // Assume authenticated if we have a user object
    }
}
