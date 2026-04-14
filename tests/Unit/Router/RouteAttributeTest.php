<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Router\Attributes\Route;

#[Route(path: '/users', methods: ['GET', 'POST'], prefix: 'api/v1', middleware: ['auth'], auth: true, authorized: ['manage_users'], name: 'users.index')]
class AnnotatedController {}

#[Route(path: '/health')]
class MinimalController {}

final class RouteAttributeTest extends TestCase
{
    public function test_attribute_is_instantiable_with_all_parameters(): void
    {
        $route = new Route(
            path: '/users',
            methods: ['GET', 'POST'],
            prefix: 'api/v1',
            target: 'index',
            middleware: ['auth', 'throttle'],
            auth: true,
            authorized: ['manage_users'],
            name: 'users.list',
        );

        $this->assertSame('/users', $route->path);
        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame('api/v1', $route->prefix);
        $this->assertSame('index', $route->target);
        $this->assertSame(['auth', 'throttle'], $route->middleware);
        $this->assertTrue($route->auth);
        $this->assertSame(['manage_users'], $route->authorized);
        $this->assertSame('users.list', $route->name);
    }

    public function test_defaults_are_sensible(): void
    {
        $route = new Route(path: '/ping');

        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('api', $route->prefix);
        $this->assertSame('__invoke', $route->target);
        $this->assertSame([], $route->middleware);
        $this->assertNull($route->auth);
        $this->assertSame([], $route->authorized);
        $this->assertNull($route->name);
    }

    public function test_route_attribute_targets_class_and_method(): void
    {
        $ref = new \ReflectionClass(Route::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        $this->assertCount(1, $attrs);

        $attribute = $attrs[0]->newInstance();
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_CLASS) !== 0);
        $this->assertTrue(($attribute->flags & \Attribute::TARGET_METHOD) !== 0);
    }

    public function test_reading_attribute_from_annotated_class(): void
    {
        $ref = new \ReflectionClass(AnnotatedController::class);
        $attrs = $ref->getAttributes(Route::class);

        $this->assertCount(1, $attrs);

        $route = $attrs[0]->newInstance();
        $this->assertSame('/users', $route->path);
        $this->assertSame(['GET', 'POST'], $route->methods);
        $this->assertSame('api/v1', $route->prefix);
        $this->assertTrue($route->auth);
        $this->assertSame(['manage_users'], $route->authorized);
        $this->assertSame('users.index', $route->name);
    }

    public function test_minimal_route_attribute_uses_defaults(): void
    {
        $ref = new \ReflectionClass(MinimalController::class);
        $attrs = $ref->getAttributes(Route::class);

        $route = $attrs[0]->newInstance();
        $this->assertSame('/health', $route->path);
        $this->assertSame(['GET'], $route->methods);
        $this->assertSame('__invoke', $route->target);
    }

    public function test_auth_accepts_string_for_guard_name(): void
    {
        $route = new Route(path: '/admin', auth: 'sanctum');

        $this->assertSame('sanctum', $route->auth);
    }
}
