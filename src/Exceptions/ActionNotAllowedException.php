<?php

declare(strict_types=1);

namespace Zolta\Http\Exceptions;

use Zolta\Exceptions\BaseException;

class ActionNotAllowedException extends BaseException
{
    protected function exceptionMessage(): string
    {
        return 'Action Not Allowed!';
    }
}
