<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Response\ResponsePayload;

final class ResponsePayloadTest extends TestCase
{
    public function test_constructs_with_all_fields(): void
    {
        $payload = new ResponsePayload(
            success: true,
            message: 'OK',
            data: ['id' => 1],
            errors: ['field' => 'required'],
            debug: ['sql' => 'SELECT 1']
        );

        $this->assertTrue($payload->success);
        $this->assertSame('OK', $payload->message);
        $this->assertSame(['id' => 1], $payload->data);
        $this->assertSame(['field' => 'required'], $payload->errors);
        $this->assertSame(['sql' => 'SELECT 1'], $payload->debug);
    }

    public function test_defaults_optional_arrays_to_empty(): void
    {
        $payload = new ResponsePayload(success: false, message: 'fail');

        $this->assertFalse($payload->success);
        $this->assertSame('fail', $payload->message);
        $this->assertSame([], $payload->data);
        $this->assertSame([], $payload->errors);
        $this->assertSame([], $payload->debug);
    }

    public function test_properties_are_readonly(): void
    {
        $payload = new ResponsePayload(success: true, message: 'ok');

        $ref = new \ReflectionClass($payload);
        foreach ($ref->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    public function test_success_payload_with_nested_data(): void
    {
        $data = [
            'user' => [
                'id' => 42,
                'name' => 'Alice',
                'roles' => ['admin', 'editor'],
            ],
        ];

        $payload = new ResponsePayload(success: true, message: 'User loaded', data: $data);

        $this->assertSame(42, $payload->data['user']['id']);
        $this->assertSame(['admin', 'editor'], $payload->data['user']['roles']);
    }

    public function test_error_payload_preserves_multiple_errors(): void
    {
        $errors = [
            'email' => 'The email field is required.',
            'password' => 'The password must be at least 8 characters.',
        ];

        $payload = new ResponsePayload(success: false, message: 'Validation failed', errors: $errors);

        $this->assertFalse($payload->success);
        $this->assertCount(2, $payload->errors);
        $this->assertArrayHasKey('email', $payload->errors);
        $this->assertArrayHasKey('password', $payload->errors);
    }
}
