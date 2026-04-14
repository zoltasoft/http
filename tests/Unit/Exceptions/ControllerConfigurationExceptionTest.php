<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Zolta\Exceptions\BaseException;
use Zolta\Http\Exceptions\ControllerConfigurationException;

final class ControllerConfigurationExceptionTest extends TestCase
{
    public function test_extends_base_exception(): void
    {
        $exception = new ControllerConfigurationException;

        $this->assertInstanceOf(BaseException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function test_default_message(): void
    {
        $exception = new ControllerConfigurationException;

        $this->assertSame('Controller configuration conflict detected.', $exception->getMessage());
    }

    public function test_message_with_controller_and_method_context(): void
    {
        $exception = new ControllerConfigurationException(
            previous: null,
            errorCode: null,
            context: ['controller' => 'UserController', 'method' => 'store']
        );

        $message = $exception->getMessage();
        $this->assertStringContainsString('UserController', $message);
        $this->assertStringContainsString('store', $message);
        $this->assertStringContainsString('configuration conflict', $message);
    }

    public function test_message_for_invalid_handler_error_code(): void
    {
        $exception = new ControllerConfigurationException(
            previous: null,
            errorCode: 'controller.configuration.invalid_handler',
            context: ['controller' => 'PostController', 'method' => 'list']
        );

        $message = $exception->getMessage();
        $this->assertStringContainsString('Invalid handler PostController::list()', $message);
        $this->assertStringContainsString('__invoke()', $message);
    }
}
