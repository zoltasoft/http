<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use Attribute;
use PHPUnit\Framework\TestCase;
use Zolta\Http\Router\Cache\ReflectionCache;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class FakeRouteAttr
{
    public function __construct(public string $path = '/test') {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
class FakeParamAttr
{
    public function __construct(public string $field = 'id') {}
}

#[FakeRouteAttr(path: '/users')]
class FakeCacheTarget
{
    public function __construct(
        public readonly string $name,
        #[FakeParamAttr(field: 'user_id')]
        public readonly int $id = 0,
    ) {}

    #[FakeRouteAttr(path: '/users/{id}')]
    public function show(): void {}

    public function noAttributes(): void {}
}

class NoConstructorTarget {}

final class ReflectionCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear static caches between tests
        $ref = new \ReflectionClass(ReflectionCache::class);
        foreach (['ctorParams', 'classAttributes', 'methodAttributes'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, []);
        }
    }

    // ── Constructor param reflection ──────────────────────────────────────

    public function test_returns_constructor_params_for_class(): void
    {
        $params = ReflectionCache::getConstructorParams(FakeCacheTarget::class);

        $this->assertCount(2, $params);
        $this->assertSame('name', $params[0]['name']);
        $this->assertSame('string', $params[0]['typeName']);
        $this->assertTrue($params[0]['isBuiltin']);
        $this->assertFalse($params[0]['isOptional']);

        $this->assertSame('id', $params[1]['name']);
        $this->assertSame('int', $params[1]['typeName']);
        $this->assertTrue($params[1]['isOptional']);
        $this->assertSame(0, $params[1]['default']);
    }

    public function test_returns_empty_for_class_without_constructor(): void
    {
        $params = ReflectionCache::getConstructorParams(NoConstructorTarget::class);

        $this->assertSame([], $params);
    }

    public function test_returns_empty_for_nonexistent_class(): void
    {
        $params = ReflectionCache::getConstructorParams('NonExistent\\Class\\Name');

        $this->assertSame([], $params);
    }

    public function test_caches_results_on_second_call(): void
    {
        $first = ReflectionCache::getConstructorParams(FakeCacheTarget::class);
        $second = ReflectionCache::getConstructorParams(FakeCacheTarget::class);

        $this->assertSame($first, $second);
    }

    public function test_parameter_attributes_captured(): void
    {
        $params = ReflectionCache::getConstructorParams(FakeCacheTarget::class);

        // The 'id' parameter has a FakeParamAttr attribute
        $idParam = $params[1];
        $attrClasses = array_column($idParam['attributes'], 'class');

        $this->assertContains(FakeParamAttr::class, $attrClasses);
    }

    // ── Method attribute reflection ───────────────────────────────────────

    public function test_returns_method_attributes(): void
    {
        $attrs = ReflectionCache::getMethodAttributes(FakeCacheTarget::class, 'show');

        $this->assertCount(1, $attrs);
        $this->assertSame(FakeRouteAttr::class, $attrs[0]['class']);
        $this->assertSame('/users/{id}', $attrs[0]['arguments']['path'] ?? $attrs[0]['arguments'][0] ?? null);
    }

    public function test_returns_empty_for_method_without_attributes(): void
    {
        $attrs = ReflectionCache::getMethodAttributes(FakeCacheTarget::class, 'noAttributes');

        $this->assertSame([], $attrs);
    }

    public function test_returns_empty_for_nonexistent_method(): void
    {
        $attrs = ReflectionCache::getMethodAttributes(FakeCacheTarget::class, 'doesNotExist');

        $this->assertSame([], $attrs);
    }

    // ── Class attribute reflection ────────────────────────────────────────

    public function test_returns_class_attributes(): void
    {
        $attrs = ReflectionCache::getClassAttributes(FakeCacheTarget::class);

        $this->assertNotEmpty($attrs);
        $attrClasses = array_column($attrs, 'class');
        $this->assertContains(FakeRouteAttr::class, $attrClasses);
    }

    public function test_returns_empty_class_attributes_for_plain_class(): void
    {
        $attrs = ReflectionCache::getClassAttributes(NoConstructorTarget::class);

        $this->assertSame([], $attrs);
    }
}
