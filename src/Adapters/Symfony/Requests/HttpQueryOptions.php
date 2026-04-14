<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Requests;

use Symfony\Component\HttpFoundation\Request;

/**
 * Symfony variant of HttpQueryOptions helper.
 */
final class HttpQueryOptions
{
    /**
     * Build a payload.
     *
     * Supported $config keys:
     * - default_include: array|string|null of relations to include when request omits the parameter.
     * - context: array of additional context flags to merge with request-provided context.
     * - strict: bool indicating whether to enable strict whitelist handling.
     * - allowed_filters: string[] whitelist applied when strict = true.
     * - allowed_sorts: string[] whitelist applied when strict = true.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function payload(Request $request, array $config = []): array
    {
        $filters = $request->query->all('filter');
        if ($filters === []) {
            $filters = $request->query->all('filters');
        }
        if ($filters === []) {
            $filters = $request->query->get('filter');
        }
        if (empty($filters)) {
            $filters = $request->query->get('filters');
        }
        $filters = self::normalizeFilters($filters);

        $include = $request->query->get('include');
        $include = self::normalizeInclude($include, $config['default_include'] ?? null);

        $sort = $request->query->get('sort');
        $sort = self::normalizeList($sort);

        $limit = $request->query->get('per_page') ?? $request->query->get('limit');
        $limit = is_numeric($limit) ? (int) $limit : null;

        $page = $request->query->get('page');
        $page = is_numeric($page) ? (int) $page : null;

        $context = $request->query->all('context');
        if ($context === []) {
            $context = $request->query->get('context');
        }
        $context = is_array($context) ? $context : (array) $context;
        if (! empty($config['context']) && is_array($config['context'])) {
            $context = array_merge($context, $config['context']);
        }

        $payload = [
            'filters' => $filters,
            'include' => $include,
            'sort' => $sort,
            'limit' => $limit,
            'page' => $page,
            'context' => $context,
        ];

        if (! empty($config['strict'])) {
            $payload['strict'] = true;
            $payload['allowed_filters'] = array_values($config['allowed_filters'] ?? []);
            $payload['allowed_sorts'] = array_values($config['allowed_sorts'] ?? []);
        }

        return $payload;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function normalizeFilters(mixed $filters): array
    {
        if ($filters === null) {
            return [];
        }

        if (is_string($filters)) {
            $decoded = json_decode($filters, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return $filters === '' ? [] : [$filters];
        }

        if (is_array($filters)) {
            return $filters;
        }

        return (array) $filters;
    }

    /**
     * @param  array<int, string>|string|null  $defaults
     * @return string[]
     */
    private static function normalizeInclude(mixed $include, array|string|null $defaults): array
    {
        $includeList = self::normalizeList($include);

        if ($includeList === [] && $defaults !== null) {
            $includeList = self::normalizeList($defaults);
        }

        return $includeList;
    }

    /**
     * @param  mixed  $value  string|array<string>|null
     * @return string[]
     */
    private static function normalizeList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn ($item): bool => $item !== ''));
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(trim(...), $value), static fn (string $item): bool => $item !== ''));
        }

        return array_values(array_filter([(string) $value], static fn (string $item): bool => $item !== ''));
    }

    private function __construct() {}
}
