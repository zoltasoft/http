<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;
use Zolta\Http\Request\Attributes\Request as RequestAttr;
use Zolta\Http\Response\Attributes\Response as ResponseAttr;
use Zolta\Http\Router\Attributes\Route as RouteAttr;
use Zolta\Http\Router\Cache\ReflectionCache;
use Zolta\Http\Service\Attributes\Doc as DocAttr;

/**
 * Generates an OpenAPI 3.0 specification from controller attributes.
 * Now also extracts DTO property schemas into components.schemas
 */
final class OpenApiGenerator
{
    /**
     * @return array<string, mixed>
     */
    public static function generate(string $basePath): array
    {
        $paths = [];
        $schemas = [];
        $finder = new Finder;
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $fqcn = self::fileToClass($file->getRealPath());
            if (! $fqcn || ! class_exists($fqcn, false)) {
                continue;
            }

            // Retrieve cached attributes
            $attrs = ReflectionCache::getClassAttributes($fqcn);
            if ($attrs === []) {
                continue;
            }

            $routeMeta = self::findAttr($attrs, RouteAttr::class);
            if (! $routeMeta) {
                continue;
            }

            $reqMeta = self::findAttr($attrs, RequestAttr::class);
            $respMeta = self::findAttr($attrs, ResponseAttr::class);
            $docMeta = self::findAttr($attrs, DocAttr::class);

            [$path, $methods, $prefix, $auth] = self::normalizeRouteArgs($routeMeta);
            $uri = '/'.trim(($prefix ? $prefix.'/' : '').$path, '/');
            $methods = array_map(strtolower(...), $methods);

            // Doc metadata
            $summary = $docMeta['arguments']['summary'] ?? $fqcn;
            $description = $docMeta['arguments']['description'] ?? '';
            $tags = $docMeta['arguments']['tags'] ?? [];

            // Detect DTO classes
            $reqDto = $reqMeta['arguments']['inputDto'] ?? ($reqMeta['arguments'][1] ?? null);
            $respRes = $respMeta['arguments']['resource'] ?? ($respMeta['arguments'][0] ?? null);

            // 🔍 Collect schemas for DTOs
            if ($reqDto && class_exists($reqDto)) {
                $schemas[class_basename($reqDto)] = self::dtoToSchema($reqDto);
            }
            if ($respRes && class_exists($respRes)) {
                $schemas[class_basename($respRes)] = self::dtoToSchema($respRes);
            }

            foreach ($methods as $method) {
                $paths[$uri][$method] = [
                    'summary' => $summary,
                    'description' => $description,
                    'tags' => $tags,
                    'security' => $auth ? [['sanctumAuth' => []]] : [],
                    'requestBody' => $reqDto ? [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/'.class_basename($reqDto),
                                ],
                            ],
                        ],
                    ] : null,
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/'.
                                            ($respRes ? class_basename($respRes) : 'GenericResource'),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Zolta Forge API',
                'version' => '1.0.0',
                'description' => 'Auto-generated API documentation (cached via ReflectionCache).',
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'sanctumAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'schemas' => $schemas,
            ],
        ];
    }

    // ===============================================================
    // SCHEMA BUILDER
    // ===============================================================

    /**
     * @return array{type:string,properties:array<string, array<string, mixed>>}
     */
    private static function dtoToSchema(string $fqcn): array
    {
        $reflectionClass = new ReflectionClass($fqcn);
        $properties = [];

        // Handle promoted properties (constructor) + declared properties
        $allProps = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($allProps as $allProp) {
            $name = $allProp->getName();
            $typeRef = $allProp->getType();
            $type = $typeRef instanceof \ReflectionNamedType ? $typeRef->getName() : 'mixed';
            $properties[$name] = self::mapTypeToSchema($type);
        }

        // Handle constructor params not declared as properties
        $ctor = $reflectionClass->getConstructor();
        if ($ctor) {
            foreach ($ctor->getParameters() as $reflectionParameter) {
                $name = $reflectionParameter->getName();
                if (isset($properties[$name])) {
                    continue;
                }

                $typeRef = $reflectionParameter->getType();
                $type = $typeRef instanceof \ReflectionNamedType ? $typeRef->getName() : 'mixed';
                $properties[$name] = self::mapTypeToSchema($type);
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapTypeToSchema(string $type): array
    {
        return match (strtolower($type)) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer', 'format' => 'int32'],
            'float', 'double' => ['type' => 'number', 'format' => 'float'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'datetime', '\datetimeinterface' => ['type' => 'string', 'format' => 'date-time'],
            'mixed' => ['type' => 'string'],
            default => ['type' => 'object', 'additionalProperties' => true],
        };
    }

    // ===============================================================
    // HELPERS
    // ===============================================================

    private static function fileToClass(string $filePath): ?string
    {
        if (! str_starts_with($filePath, app_path())) {
            return null;
        }
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $filePath);

        return 'App\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
    }

    /**
     * @param  array<int, array<string, mixed>>  $attrs
     * @return array<string, mixed>|null
     */
    private static function findAttr(array $attrs, string $target): ?array
    {
        foreach ($attrs as $attr) {
            if ($attr['class'] === $target) {
                return $attr;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attr
     * @return array{0:mixed,1:array<int,string>|string,2:string|null,3:bool|string}
     */
    private static function normalizeRouteArgs(array $attr): array
    {
        $args = $attr['arguments'] ?? [];
        $path = $args[0] ?? '';
        $methods = $args['methods'] ?? ($args[1] ?? ['GET']);
        $prefix = $args['prefix'] ?? ($args[2] ?? null);
        $auth = $args['auth'] ?? ($args[3] ?? false);

        return [$path, $methods, $prefix, $auth];
    }
}
