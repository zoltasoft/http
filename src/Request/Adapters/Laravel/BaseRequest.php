<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel;

use Zolta\Http\Exceptions\Traits\HandlesApiExceptions;
use Zolta\Http\Request\Laravel\Bridge\LaravelBridgeRequest;
use Zolta\Http\Request\Laravel\Traits\HandlesValidationFailure;
use Zolta\Http\Request\Traits\UseRequestQueryParams;
use Zolta\Http\Request\Traits\UseRequestRouteParams;

/**
 * Concrete Laravel base request (used by application FormRequests).
 */
abstract class BaseRequest extends LaravelBridgeRequest
{
    use HandlesApiExceptions,
        HandlesValidationFailure,
        UseRequestQueryParams,
        UseRequestRouteParams {
            UseRequestQueryParams::transformValue insteadof UseRequestRouteParams;
            UseRequestQueryParams::setNestedValue insteadof UseRequestRouteParams;
        }
}
