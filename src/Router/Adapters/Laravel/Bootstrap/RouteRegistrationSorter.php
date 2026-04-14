<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

/**
 * Sorts discovered route definitions so that the most specific routes
 * are registered first.
 *
 * Laravel routing is ORDER SENSITIVE. If sorting is wrong, routes like:
 *   /users/{id}
 * can shadow:
 *   /users/me
 *   /users/by-email/{email}
 *
 * Priority rules implemented here (highest → lowest):
 *
 * 1) More STATIC segments first
 * 2) More TOTAL segments (deeper routes) first
 * 3) Routes WITHOUT optional params `{id?}` first
 * 4) Routes WITHOUT greedy/wildcard params last (`{any}`, `{slug}`, `{path}`)
 * 5) Stable alphabetical fallback
 */
final class RouteRegistrationSorter
{
    /**
     * @param  list<array{code:string,middleware:list<string>}|string>  $routes
     * @return list<array{code:string,middleware:list<string>}|string>
     */
    public static function sort(array $routes): array
    {
        if (count($routes) <= 1) {
            return $routes;
        }

        usort($routes, static function (array|string $a, array|string $b): int {
            $uriA = self::extractUri($a);
            $uriB = self::extractUri($b);

            return self::compareUris($uriA, $uriB);
        });

        return array_values($routes);
    }

    /**
     * Compare two URI paths for registration priority.
     * Returns < 0 when $a should be registered first.
     */
    public static function compareUris(string $a, string $b): int
    {
        // 1) Routes with more STATIC segments win
        $staticA = self::countStaticSegments($a);
        $staticB = self::countStaticSegments($b);

        if ($staticA !== $staticB) {
            return $staticB <=> $staticA;
        }

        // 2) Deeper routes win
        $depthA = count(self::segments($a));
        $depthB = count(self::segments($b));

        if ($depthA !== $depthB) {
            return $depthB <=> $depthA;
        }

        // 3) Routes WITHOUT optional params `{id?}` win
        $optionalA = self::countOptionalParams($a);
        $optionalB = self::countOptionalParams($b);

        if ($optionalA !== $optionalB) {
            return $optionalA <=> $optionalB;
        }

        // 4) Routes WITHOUT wildcard params win LAST
        $wildA = self::countWildcardParams($a);
        $wildB = self::countWildcardParams($b);

        if ($wildA !== $wildB) {
            return $wildA <=> $wildB;
        }

        // 5) Stable alphabetical fallback
        return strcmp($a, $b);
    }

    /**
     * Extract URI from route code.
     */
    public static function extractUri(array|string $route): string
    {
        $code = is_array($route) ? ($route['code'] ?? '') : (string) $route;

        // Route::match([...], '/uri', ...)
        if (preg_match("#Route::match\([^,]+,\s*'([^']*)'#", $code, $m)) {
            return $m[1];
        }

        // Route::get('/uri', ...)
        if (preg_match("#Route::\w+\(\s*'([^']*)'#", $code, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Split URI into segments.
     *
     * @return list<string>
     */
    private static function segments(string $uri): array
    {
        $trimmed = trim($uri, '/');

        if ($trimmed === '') {
            return [];
        }

        return array_values(
            array_filter(explode('/', $trimmed), fn($s) => $s !== '')
        );
    }

    /**
     * Check if segment is `{param}`.
     */
    private static function isDynamic(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
    }

    /**
     * Count static segments.
     */
    private static function countStaticSegments(string $uri): int
    {
        return count(array_filter(
            self::segments($uri),
            fn($seg) => ! self::isDynamic($seg)
        ));
    }

    /**
     * Count optional params `{id?}`.
     */
    private static function countOptionalParams(string $uri): int
    {
        return count(array_filter(
            self::segments($uri),
            fn($seg) => str_contains($seg, '?')
        ));
    }

    /**
     * Count greedy/wildcard params.
     */
    private static function countWildcardParams(string $uri): int
    {
        return count(array_filter(
            self::segments($uri),
            fn($seg) => preg_match('/\{(any|slug|path|catchAll).*}/i', $seg)
        ));
    }
}
