<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Exceptions\ActionNotAllowedException;

final readonly class LaravelAuthorizationService implements AuthorizationServiceInterface
{
    public function __construct(private Gate $gate) {}

    public function ensureAuthorized(string $action, mixed $subject = null): void
    {
        if (AuthorizationMatrix::isGranted($action)) {
            return;
        }

        if (! $this->gate->allows($action, $subject)) {
            throw new ActionNotAllowedException;
        }
    }
}
