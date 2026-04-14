<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Contracts;

use Illuminate\Http\Request;

interface HTTP
{
    /**
     * Build a framework-agnostic Request object from
     * the current native framework request.
     */
    public function request(): Request|\Symfony\Component\HttpFoundation\Request;
}
