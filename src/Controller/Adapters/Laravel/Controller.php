<?php

declare(strict_types=1);

namespace Zolta\Http\Controller\Laravel;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Traits\Macroable;

/**
 * Laravel HTTP Controller Base Class
 *
 * Provides a base controller class for HTTP module controllers.
 * Extends Laravel's base controller to maintain compatibility.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, Macroable, ValidatesRequests;

    // Base controller functionality can be extended here
    // This provides a common base for all HTTP controllers
}
