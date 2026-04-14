<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Zolta\Exceptions\BaseException;
use Zolta\Http\Exceptions\ActionNotAllowedException;

final class ActionNotAllowedExceptionTest extends TestCase
{
    public function test_extends_base_exception(): void
    {
        $exception = new ActionNotAllowedException;

        $this->assertInstanceOf(BaseException::class, $exception);
    }

    public function test_has_correct_message(): void
    {
        $exception = new ActionNotAllowedException;

        $this->assertSame('Action Not Allowed!', $exception->getMessage());
    }

    public function test_default_code_is_400(): void
    {
        $exception = new ActionNotAllowedException;

        $this->assertSame(400, $exception->getCode());
    }

    public function test_accepts_custom_status(): void
    {
        $exception = new ActionNotAllowedException(status: 403);

        $this->assertSame(403, $exception->getCode());
    }

    public function test_preserves_previous_exception(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new ActionNotAllowedException(previous: $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
