<?php

declare(strict_types=1);

namespace Zolta\Http\Exceptions\Traits;

use Throwable;
use Zolta\Exceptions\BaseException;
use Zolta\Exceptions\Rest\InternalServerErrorException;
use Zolta\Exceptions\ValidationException;

trait HandlesApiExceptions
{
    /**
     * Run callback and normalize exceptions:
     * - Domain/application exceptions are rethrown
     * - Other Throwables are converted to InternalServerErrorException
     *
     * @return mixed
     *
     * @throws BaseException|ValidationException|InternalServerErrorException
     */
    public function handleExceptions(callable $callback)
    {
        try {
            return $callback();
        } catch (ValidationException|BaseException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InternalServerErrorException($e);
        }
    }
}
