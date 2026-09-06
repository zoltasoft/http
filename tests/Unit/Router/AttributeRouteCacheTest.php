<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zolta\Http\Router\Laravel\Bootstrap\AttributeRouteCache;
use Zolta\Http\Router\Laravel\Bootstrap\RouteRegistrationSorter;

final class AttributeRouteCacheTest extends TestCase
{
    public function test_flattening_routes_from_multiple_files_preserves_route_precedence(): void
    {
        $cacheReflection = new ReflectionClass(AttributeRouteCache::class);
        $cache = $cacheReflection->newInstanceWithoutConstructor();
        $routesByFile = $cacheReflection->getProperty('routesByFile');
        $routesByFile->setValue($cache, [
            'app/Services/Jobs/ShowJobController.php' => [
                $this->routeEntry('/api/jobs/{id}'),
            ],
            'app/Services/Jobs/ListMyJobsController.php' => [
                $this->routeEntry('/api/jobs/my'),
            ],
        ]);

        $flatten = $cacheReflection->getMethod('flattenRoutesByFile');
        /** @var list<array{code: string, middleware: list<string>}> $routes */
        $routes = $flatten->invoke($cache);

        $this->assertSame([
            '/api/jobs/my',
            '/api/jobs/{id}',
        ], array_map([RouteRegistrationSorter::class, 'extractUri'], $routes));
    }

    /**
     * @return array{code: string, middleware: list<string>}
     */
    private function routeEntry(string $uri): array
    {
        return [
            'code' => "Route::match(array ('GET', 'HEAD'), '{$uri}', [\\Controller::class, '__invoke'])",
            'middleware' => ['api'],
        ];
    }
}
