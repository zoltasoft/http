<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Requests;

use Zolta\Http\Exceptions\Traits\HandlesApiExceptions;
use Zolta\Http\Request\Traits\UseRequestQueryParams;
use Zolta\Http\Request\Traits\UseRequestRouteParams;
use Zolta\Http\Symfony\Requests\Bridge\SymfonyBridgeRequest;

/**
 * Symfony base request for host apps.
 *
 * Mirrors the Laravel BaseRequest API (route/query params merging, DTO mapping)
 * while relying on Symfony's RequestStack + validator/authorization adapters.
 */
abstract class BaseRequest extends SymfonyBridgeRequest
{
    use HandlesApiExceptions,
        UseRequestQueryParams,
        UseRequestRouteParams {
            UseRequestQueryParams::transformValue insteadof UseRequestRouteParams;
            UseRequestQueryParams::setNestedValue insteadof UseRequestRouteParams;
        }
}
