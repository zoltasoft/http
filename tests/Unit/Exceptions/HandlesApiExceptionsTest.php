<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Zolta\Exceptions\BaseException;
use Zolta\Exceptions\Rest\InternalServerErrorException;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Exceptions\Traits\HandlesApiExceptions;

final class HandlesApiExceptionsTest extends TestCase
{
    private object $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new class
        {
            use HandlesApiExceptions;
        };
    }

    public function test_returns_callback_value_on_success(): void
    {
        $result = $this->handler->handleExceptions(fn () => 'ok');

        $this->assertSame('ok', $result);
    }

    public function test_rethrows_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handleExceptions(function () {
            throw new ValidationException(['email' => 'required']);
        });
    }

    public function test_rethrows_base_exception(): void
    {
        $this->expectException(BaseException::class);

        $original = new class extends BaseException
        {
            protected function exceptionMessage(): string
            {
                return 'Domain error';
            }
        };

        $this->handler->handleExceptions(function () use ($original) {
            throw $original;
        });
    }

    public function test_wraps_generic_throwable_in_internal_server_error(): void
    {
        try {
            $this->handler->handleExceptions(function () {
                throw new \RuntimeException('unexpected failure');
            });
            $this->fail('Expected InternalServerErrorException');
        } catch (InternalServerErrorException $e) {
            $this->assertInstanceOf(InternalServerErrorException::class, $e);
            $this->assertNotNull($e->getPrevious());
            $this->assertSame('unexpected failure', $e->getPrevious()->getMessage());
        }
    }

    public function test_wraps_type_error_in_internal_server_error(): void
    {
        $this->expectException(InternalServerErrorException::class);

        $this->handler->handleExceptions(function () {
            throw new \TypeError('Argument must be of type int');
        });
    }

    public function test_callback_can_return_null(): void
    {
        $result = $this->handler->handleExceptions(fn () => null);

        $this->assertNull($result);
    }

    public function test_callback_can_return_array(): void
    {
        $result = $this->handler->handleExceptions(fn () => ['data' => [1, 2, 3]]);

        $this->assertSame(['data' => [1, 2, 3]], $result);
    }
}
