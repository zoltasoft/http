<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Interfaces;

interface AuthorizationServiceInterface
{
    /**
     * Ensure the current actor is authorized for $action on $subject.
     * Throws ActionNotAllowedException on failure.
     */
    public function ensureAuthorized(string $action, mixed $subject = null): void;
}
