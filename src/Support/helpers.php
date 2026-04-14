<?php

declare(strict_types=1);

use Zolta\Http\Authorization\Interfaces\UserIdentityInterface;
use Zolta\Http\Authorization\Identity;

if (! function_exists('identity')) {
    /**
     * Retrieve the current identity (if resolved by the framework adapters).
     */
    function identity(): ?UserIdentityInterface
    {
        return Identity::current();
    }
}

if (! function_exists('user')) {
    /**
     * Retrieve the domain user from the current identity, if available.
     */
    function user(): mixed
    {
        $identity = identity();
        if ($identity && method_exists($identity, 'getDomainUser')) {
            return $identity->getDomainUser();
        }

        return null;
    }
}
