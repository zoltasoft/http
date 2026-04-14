<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Contracts;

/**
 * Abstraction exposing the underlying router implementation for the current framework.
 */
interface RouterProvider
{
    /**
     * Return the current framework's router instance.
     */
    public function router(): mixed;
}
