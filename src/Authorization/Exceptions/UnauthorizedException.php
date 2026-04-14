<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Exceptions;

use Exception;

/**
 * Exception thrown when authorization fails.
 */
final class UnauthorizedException extends Exception
{
    public function __construct(
        string $message = 'Unauthorized access.',
        int $code = 403,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
