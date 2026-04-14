<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Response\Attributes\Response;

#[Request(formRequest: 'App\\Requests\\StoreUserRequest', inputDto: 'App\\DTOs\\StoreUserDto')]
class RequestAnnotatedController {}

#[Request(formRequest: 'App\\Requests\\ListRequest')]
class MinimalRequestController {}

#[Response(resource: 'App\\Resources\\UserResource', method: 'toDetailArray')]
class ResponseAnnotatedController {}

#[Response(resource: 'App\\Resources\\ListResource')]
class MinimalResponseController {}

final class RequestResponseAttributeTest extends TestCase
{
    // ── Request Attribute ─────────────────────────────────────────────────

    public function test_request_attribute_with_all_params(): void
    {
        $attr = new Request(
            formRequest: 'App\\Requests\\StoreUserRequest',
            inputDto: 'App\\DTOs\\StoreUserDto',
        );

        $this->assertSame('App\\Requests\\StoreUserRequest', $attr->formRequest);
        $this->assertSame('App\\DTOs\\StoreUserDto', $attr->inputDto);
    }

    public function test_request_attribute_defaults_dto_to_null(): void
    {
        $attr = new Request(formRequest: 'App\\Requests\\BasicRequest');

        $this->assertNull($attr->inputDto);
    }

    public function test_request_attribute_targets_class_and_method(): void
    {
        $ref = new \ReflectionClass(Request::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $attribute = $attrs[0]->newInstance();

        $this->assertTrue(($attribute->flags & \Attribute::TARGET_CLASS) !== 0);
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    public function test_request_attribute_read_from_class(): void
    {
        $ref = new \ReflectionClass(RequestAnnotatedController::class);
        $attrs = $ref->getAttributes(Request::class);

        $this->assertCount(1, $attrs);
        $attr = $attrs[0]->newInstance();
        $this->assertSame('App\\Requests\\StoreUserRequest', $attr->formRequest);
        $this->assertSame('App\\DTOs\\StoreUserDto', $attr->inputDto);
    }

    public function test_minimal_request_attribute_from_class(): void
    {
        $ref = new \ReflectionClass(MinimalRequestController::class);
        $attr = $ref->getAttributes(Request::class)[0]->newInstance();

        $this->assertSame('App\\Requests\\ListRequest', $attr->formRequest);
        $this->assertNull($attr->inputDto);
    }

    // ── Response Attribute ────────────────────────────────────────────────

    public function test_response_attribute_with_all_params(): void
    {
        $attr = new Response(
            resource: 'App\\Resources\\OrderResource',
            method: 'toDetailArray',
        );

        $this->assertSame('App\\Resources\\OrderResource', $attr->resource);
        $this->assertSame('toDetailArray', $attr->method);
    }

    public function test_response_attribute_defaults_method_to_to_array(): void
    {
        $attr = new Response(resource: 'App\\Resources\\UserResource');

        $this->assertSame('toArray', $attr->method);
    }

    public function test_response_attribute_targets_class_and_method(): void
    {
        $ref = new \ReflectionClass(Response::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $attribute = $attrs[0]->newInstance();

        $this->assertTrue(($attribute->flags & \Attribute::TARGET_CLASS) !== 0);
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    public function test_response_attribute_read_from_class(): void
    {
        $ref = new \ReflectionClass(ResponseAnnotatedController::class);
        $attr = $ref->getAttributes(Response::class)[0]->newInstance();

        $this->assertSame('App\\Resources\\UserResource', $attr->resource);
        $this->assertSame('toDetailArray', $attr->method);
    }

    public function test_minimal_response_attribute_from_class(): void
    {
        $ref = new \ReflectionClass(MinimalResponseController::class);
        $attr = $ref->getAttributes(Response::class)[0]->newInstance();

        $this->assertSame('App\\Resources\\ListResource', $attr->resource);
        $this->assertSame('toArray', $attr->method);
    }
}
