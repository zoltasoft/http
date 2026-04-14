<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Router\Laravel\Bootstrap\RouteRegistrationSorter;

final class RouteRegistrationSorterTest extends TestCase
{
    // ------------------------------------------------------------------
    // sort() — full integration with route entry arrays
    // ------------------------------------------------------------------

    public function test_static_routes_are_registered_before_parameterised(): void
    {
        $routes = [
            $this->routeEntry('/users/{id}', 'GET'),
            $this->routeEntry('/users/by-email/{email}', 'GET'),
            $this->routeEntry('/users/provision-access', 'POST'),
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        $this->assertSame([
            '/users/by-email/{email}',
            '/users/provision-access',
            '/users/{id}',
        ], $uris);
    }

    public function test_deeper_static_routes_come_before_shallower_wildcards(): void
    {
        $routes = [
            $this->routeEntry('/api/{resource}', 'GET'),
            $this->routeEntry('/api/users/export', 'GET'),
            $this->routeEntry('/api/users/{id}', 'GET'),
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        $this->assertSame([
            '/api/users/export',
            '/api/users/{id}',
            '/api/{resource}',
        ], $uris);
    }

    public function test_root_route_sorts_last(): void
    {
        $routes = [
            $this->routeEntry('/', 'GET'),
            $this->routeEntry('/health', 'GET'),
            $this->routeEntry('/users/{id}', 'GET'),
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        $this->assertSame([
            '/health',
            '/users/{id}',
            '/',
        ], $uris);
    }

    public function test_same_depth_static_routes_are_sorted_alphabetically(): void
    {
        $routes = [
            $this->routeEntry('/users/settings', 'GET'),
            $this->routeEntry('/users/profile', 'GET'),
            $this->routeEntry('/users/account', 'GET'),
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        $this->assertSame([
            '/users/account',
            '/users/profile',
            '/users/settings',
        ], $uris);
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame([], RouteRegistrationSorter::sort([]));
    }

    public function test_single_route_returns_unchanged(): void
    {
        $routes = [$this->routeEntry('/users', 'GET')];
        $sorted = RouteRegistrationSorter::sort($routes);

        $this->assertCount(1, $sorted);
        $this->assertSame('/users', RouteRegistrationSorter::extractUri($sorted[0]));
    }

    public function test_real_world_user_management_routes(): void
    {
        $routes = [
            $this->routeEntry('/users/{id}', 'DELETE'),
            $this->routeEntry('/users/by-email/{email}', 'DELETE'),
            $this->routeEntry('/users/{id}/email', 'PATCH'),
            $this->routeEntry('/users/by-email/{email}', 'GET'),
            $this->routeEntry('/users/{id}', 'GET'),
            $this->routeEntry('/users/provision-access', 'POST'),
            $this->routeEntry('/users', 'GET'),
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        // Static second-segments before dynamic ones
        // by-email/{email} and provision-access before {id}
        // {id}/email before plain {id} (more segments = more specific)
        foreach ($uris as $i => $uri) {
            if ($uri === '/users/{id}') {
                // Ensure /users/by-email, /users/provision-access, /users/{id}/email
                // are all registered before /users/{id}
                $beforeIds = array_slice($uris, 0, $i);
                $this->assertContains('/users/by-email/{email}', $beforeIds, '/users/by-email/{email} (GET) should precede /users/{id}');
                $this->assertContains('/users/provision-access', $beforeIds, '/users/provision-access should precede /users/{id}');
                break;
            }
        }

        // Verify /users/{id}/email comes before /users/{id}
        $emailIdx = array_search('/users/{id}/email', $uris);
        $idxSimpleGet = null;
        foreach ($uris as $k => $u) {
            if ($u === '/users/{id}') {
                $idxSimpleGet = $k;
                break;
            }
        }
        $this->assertNotNull($emailIdx);
        $this->assertNotNull($idxSimpleGet);
        $this->assertLessThan($idxSimpleGet, $emailIdx, '/users/{id}/email should precede /users/{id}');
    }

    // ------------------------------------------------------------------
    // compareUris() — low-level comparison
    // ------------------------------------------------------------------

    public function test_compare_static_before_dynamic(): void
    {
        $this->assertLessThan(0, RouteRegistrationSorter::compareUris('/users/profile', '/users/{id}'));
        $this->assertGreaterThan(0, RouteRegistrationSorter::compareUris('/users/{id}', '/users/profile'));
    }

    public function test_compare_equal_uris_returns_zero(): void
    {
        $this->assertSame(0, RouteRegistrationSorter::compareUris('/users/{id}', '/users/{id}'));
        $this->assertSame(0, RouteRegistrationSorter::compareUris('/health', '/health'));
    }

    public function test_compare_deeper_path_before_shorter_when_prefix_matches(): void
    {
        $this->assertLessThan(0, RouteRegistrationSorter::compareUris('/users/{id}/email', '/users/{id}'));
    }

    public function test_compare_both_dynamic_proceeds_to_next_segment(): void
    {
        // Both have dynamic first segment, but second differs
        $this->assertLessThan(0, RouteRegistrationSorter::compareUris('/{type}/static', '/{type}/{id}'));
    }

    // ------------------------------------------------------------------
    // extractUri()
    // ------------------------------------------------------------------

    public function test_extract_uri_from_route_match_code(): void
    {
        $code = "Route::match(array ('GET'), '/users/{id}', [\\Controller::class, '__invoke'])";
        $this->assertSame('/users/{id}', RouteRegistrationSorter::extractUri($code));
    }

    public function test_extract_uri_from_array_entry(): void
    {
        $entry = ['code' => "Route::match(['GET'], '/api/health', [\\C::class, 'x'])", 'middleware' => []];
        $this->assertSame('/api/health', RouteRegistrationSorter::extractUri($entry));
    }

    public function test_extract_uri_returns_empty_for_malformed_input(): void
    {
        $this->assertSame('', RouteRegistrationSorter::extractUri('not a route'));
        $this->assertSame('', RouteRegistrationSorter::extractUri(['code' => '', 'middleware' => []]));
    }

    // ------------------------------------------------------------------
    // string input (plain code strings)
    // ------------------------------------------------------------------

    public function test_sort_handles_plain_string_entries(): void
    {
        $routes = [
            "Route::match(array ('GET'), '/orders/{id}', [\\C::class, 'x'])",
            "Route::match(array ('GET'), '/orders/pending', [\\C::class, 'x'])",
        ];

        $sorted = RouteRegistrationSorter::sort($routes);
        $uris = array_map([RouteRegistrationSorter::class, 'extractUri'], $sorted);

        $this->assertSame(['/orders/pending', '/orders/{id}'], $uris);
    }

    /**
     * @param  list<string>  $methods
     * @return array{code:string,middleware:list<string>}
     */
    private function routeEntry(string $uri, string ...$methods): array
    {
        $methods = $methods ?: ['GET'];
        $methodsStr = "array (" . implode(', ', array_map(fn(string $m): string => "'$m'", $methods)) . ")";

        return [
            'code' => "Route::match({$methodsStr}, '{$uri}', [\\Zolta\\Http\\Router\\Laravel\\Bootstrap\\AutoInvokeProxyController::class, '__invoke'])\n    ->middleware(array ('api'))\n    ->name('test');",
            'middleware' => ['api'],
        ];
    }
}
