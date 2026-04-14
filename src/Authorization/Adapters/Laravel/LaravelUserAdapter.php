<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Laravel;

use Zolta\Http\Authorization\UserAdapter;

/**
 * Laravel-specific user adapter that wraps Laravel User models
 * and implements the UserInterface contract.
 */
final class LaravelUserAdapter extends UserAdapter
{
    /**
     * Get the unique identifier for this Laravel user.
     * Assumes Laravel User model has getKey() or id property.
     */
    public function getId(): string|int
    {
        // Try getKey() first (Laravel Eloquent method)
        if (method_exists($this->user, 'getKey')) {
            return $this->user->getKey();
        }

        // Last resort - use object hash as identifier
        return $this->user->id ?? spl_object_hash($this->user);
    }
}
