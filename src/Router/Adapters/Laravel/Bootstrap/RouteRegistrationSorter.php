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
 * 1) At the first overlapping segment, STATIC beats DYNAMIC
 * 2) More STATIC segments first
 * 3) More TOTAL segments (deeper routes) first
 * 4) Routes WITHOUT optional params `{id?}` first
 * 5) Routes WITHOUT conventional wildcard params first
 * 6) Deterministic alphabetical fallback
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

        return $routes;
    }

    /**
     * Compare two URI paths for registration priority.
     * Returns < 0 when $a should be registered first.
     */
    public static function compareUris(string $a, string $b): int
    {
        $segmentsA = self::segments($a);
        $segmentsB = self::segments($b);
        $sharedDepth = min(count($segmentsA), count($segmentsB));

        // A static segment must beat a dynamic segment at the same position.
        // Global static-segment counts cannot express this safely: a route may
        // contain more static segments later while an earlier parameter has
        // already shadowed its static sibling.
        for ($index = 0; $index < $sharedDepth; $index++) {
            $segmentA = $segmentsA[$index];
            $segmentB = $segmentsB[$index];

            if ($segmentA === $segmentB) {
                continue;
            }

            $dynamicA = self::isDynamic($segmentA);
            $dynamicB = self::isDynamic($segmentB);

            if ($dynamicA !== $dynamicB) {
                return $dynamicA <=> $dynamicB;
            }

            // Different static literals cannot shadow one another, so their
            // remaining segments do not affect precedence.
            if (! $dynamicA) {
                break;
            }
        }

        // 2) Routes with more STATIC segments win
        $staticA = self::countStaticSegments($a);
        $staticB = self::countStaticSegments($b);

        if ($staticA !== $staticB) {
            return $staticB <=> $staticA;
        }

        // 3) Deeper routes win
        $depthA = count($segmentsA);
        $depthB = count($segmentsB);

        if ($depthA !== $depthB) {
            return $depthB <=> $depthA;
        }

        // 4) Routes WITHOUT optional params `{id?}` win
        $optionalA = self::countOptionalParams($a);
        $optionalB = self::countOptionalParams($b);

        if ($optionalA !== $optionalB) {
            return $optionalA <=> $optionalB;
        }

        // 5) Routes WITHOUT conventional wildcard params win
        $wildA = self::countWildcardParams($a);
        $wildB = self::countWildcardParams($b);

        if ($wildA !== $wildB) {
            return $wildA <=> $wildB;
        }

        // 6) Deterministic alphabetical fallback
        return strcmp($a, $b);
    }

    /**
     * Extract URI from route code.
     */
    public static function extractUri(array|string $route): string
    {
        $code = is_array($route) ? ($route['code'] ?? '') : (string) $route;

        // Route::match([...], '/uri', ...), including multi-method arrays.
        if (preg_match("#Route::match\(\s*(?:array\s*\(.*?\)|\[.*?\])\s*,\s*(['\"])(.*?)\\1\s*,#s", $code, $matches)) {
            return $matches[2];
        }

        // Route::get('/uri', ...) and equivalent verb helpers.
        if (preg_match("#Route::\w+\(\s*(['\"])(.*?)\\1\s*,#s", $code, $matches)) {
            return $matches[2];
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
            array_filter(explode('/', $trimmed), fn ($s): bool => $s !== '')
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
            fn (string $seg): bool => ! self::isDynamic($seg)
        ));
    }

    /**
     * Count optional params `{id?}`.
     */
    private static function countOptionalParams(string $uri): int
    {
        return count(array_filter(
            self::segments($uri),
            fn (string $seg): bool => str_contains($seg, '?')
        ));
    }

    /**
     * Count greedy/wildcard params.
     */
    private static function countWildcardParams(string $uri): int
    {
        return count(array_filter(
            self::segments($uri),
            fn (string $seg): int|false => preg_match('/\{(any|slug|path|catchAll).*}/i', $seg)
        ));
    }
}
