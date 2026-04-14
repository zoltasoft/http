<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Service\Attributes\Controller\Resource;
use Zolta\Http\Service\Attributes\Doc;
use Zolta\Http\Service\Attributes\Service;

#[Service(class: 'App\\Services\\UserService', successMessage: 'User created', status: 201)]
class ServiceAnnotatedController {}

#[Service(class: 'App\\Services\\ListService')]
class MinimalServiceController {}

#[Doc(summary: 'List users', description: 'Returns paginated users', tags: ['users', 'admin'])]
class DocumentedController {}

#[Resource(class: 'App\\Resources\\UserResource')]
class ResourceAnnotatedController {}

final class ServiceAttributesTest extends TestCase
{
    // ── Service Attribute ─────────────────────────────────────────────────

    public function test_service_attribute_with_all_params(): void
    {
        $service = new Service(
            class: 'App\\Services\\OrderService',
            successMessage: 'Order placed',
            status: 201,
        );

        $this->assertSame('App\\Services\\OrderService', $service->class);
        $this->assertSame('Order placed', $service->successMessage);
        $this->assertSame(201, $service->status);
    }

    public function test_service_attribute_defaults(): void
    {
        $service = new Service(class: 'App\\Services\\FooService');

        $this->assertSame('Request completed successfully.', $service->successMessage);
        $this->assertSame(200, $service->status);
    }

    public function test_service_attribute_read_from_class(): void
    {
        $ref = new \ReflectionClass(ServiceAnnotatedController::class);
        $attrs = $ref->getAttributes(Service::class);

        $this->assertCount(1, $attrs);
        $service = $attrs[0]->newInstance();
        $this->assertSame('App\\Services\\UserService', $service->class);
        $this->assertSame('User created', $service->successMessage);
        $this->assertSame(201, $service->status);
    }

    public function test_service_targets_class_and_method(): void
    {
        $ref = new \ReflectionClass(Service::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $attribute = $attrs[0]->newInstance();

        $this->assertTrue(($attribute->flags & \Attribute::TARGET_CLASS) !== 0);
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    // ── Doc Attribute ─────────────────────────────────────────────────────

    public function test_doc_attribute_with_all_params(): void
    {
        $doc = new Doc(
            summary: 'Create order',
            description: 'Creates a new order in the system',
            tags: ['orders', 'commerce'],
        );

        $this->assertSame('Create order', $doc->summary);
        $this->assertSame('Creates a new order in the system', $doc->description);
        $this->assertSame(['orders', 'commerce'], $doc->tags);
    }

    public function test_doc_attribute_defaults(): void
    {
        $doc = new Doc;

        $this->assertSame('', $doc->summary);
        $this->assertSame('', $doc->description);
        $this->assertSame([], $doc->tags);
    }

    public function test_doc_attribute_read_from_class(): void
    {
        $ref = new \ReflectionClass(DocumentedController::class);
        $attrs = $ref->getAttributes(Doc::class);

        $this->assertCount(1, $attrs);
        $doc = $attrs[0]->newInstance();
        $this->assertSame('List users', $doc->summary);
        $this->assertSame(['users', 'admin'], $doc->tags);
    }

    // ── Resource Attribute ────────────────────────────────────────────────

    public function test_resource_attribute(): void
    {
        $resource = new Resource(class: 'App\\Resources\\UserResource');

        $this->assertSame('App\\Resources\\UserResource', $resource->class);
    }

    public function test_resource_attribute_read_from_class(): void
    {
        $ref = new \ReflectionClass(ResourceAnnotatedController::class);
        $attrs = $ref->getAttributes(Resource::class);

        $this->assertCount(1, $attrs);
        $resource = $attrs[0]->newInstance();
        $this->assertSame('App\\Resources\\UserResource', $resource->class);
    }
}
