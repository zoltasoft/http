<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;
use Zolta\Http\Router\Attributes\Route as RouteAttr;
use Zolta\Http\Router\Cache\ReflectionCache;
use Zolta\Http\Router\Laravel\Bootstrap\Metadata\RouteMetadataResolver;
use Zolta\Http\Service\Attributes\Doc as DocAttr;

/**
 * Generates an OpenAPI 3.0 document from the same attributes used to register
 * routes at runtime. The generated shape is intentionally optimized for API
 * client importers such as Postman.
 */
final class OpenApiGenerator
{
    /**
     * @return array<string, mixed>
     */
    public static function generate(string $basePath): array
    {
        $operations = [];
        $schemas = [];

        $finder = new Finder;
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            $fqcn = AttributeRouteLoader::resolveControllerClassFromFile($realPath);
            if ($fqcn === null) {
                continue;
            }

            $fragment = self::generateForController($fqcn, $realPath);
            $operations = array_merge($operations, $fragment['operations']);
            $schemas = array_replace($schemas, $fragment['schemas']);
        }

        return self::compileDocument($operations, $schemas);
    }

    /**
     * @return array{
     *     operations:list<array{path:string,method:string,operation:array<string,mixed>}>,
     *     schemas:array<string,array<string,mixed>>
     * }
     */
    public static function generateForController(string $controllerClass, ?string $sourceFile = null): array
    {
        if (! class_exists($controllerClass) && ($sourceFile !== null && is_file($sourceFile))) {
            require_once $sourceFile;
        }

        if (! class_exists($controllerClass)) {
            return ['operations' => [], 'schemas' => []];
        }

        $reflectionClass = new ReflectionClass($controllerClass);
        $classAttributes = ReflectionCache::getClassAttributes($controllerClass);
        $operations = [];
        $schemas = [];

        foreach ($classAttributes as $classAttribute) {
            if ($classAttribute['class'] !== RouteAttr::class) {
                continue;
            }

            $fragment = self::buildFragment(
                controllerClass: $controllerClass,
                targetMethod: '__invoke',
                routeArguments: $classAttribute['arguments'],
                classAttributes: $classAttributes,
                methodAttributes: [],
                requestSourceFile: $sourceFile,
            );

            $operations = array_merge($operations, $fragment['operations']);
            $schemas = array_replace($schemas, $fragment['schemas']);
        }

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $methodAttributes = ReflectionCache::getMethodAttributes($controllerClass, $reflectionMethod->getName());
            foreach ($methodAttributes as $methodAttribute) {
                if ($methodAttribute['class'] !== RouteAttr::class) {
                    continue;
                }

                $fragment = self::buildFragment(
                    controllerClass: $controllerClass,
                    targetMethod: $reflectionMethod->getName(),
                    routeArguments: $methodAttribute['arguments'],
                    classAttributes: $classAttributes,
                    methodAttributes: $methodAttributes,
                    requestSourceFile: $sourceFile,
                );

                $operations = array_merge($operations, $fragment['operations']);
                $schemas = array_replace($schemas, $fragment['schemas']);
            }
        }

        return [
            'operations' => self::uniqueOperations($operations),
            'schemas' => $schemas,
        ];
    }

    /**
     * @param  list<array{path:string,method:string,operation:array<string,mixed>}>  $operations
     * @param  array<string,array<string,mixed>>  $schemas
     * @return array<string, mixed>
     */
    public static function compileDocument(array $operations, array $schemas): array
    {
        $paths = [];
        foreach ($operations as $operation) {
            $paths[$operation['path']][$operation['method']] = $operation['operation'];
        }

        ksort($paths);
        foreach ($paths as &$methods) {
            ksort($methods);
        }
        unset($methods);

        $document = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => (string) config('zolta-http.routes.documentation.title', 'API Documentation'),
                'version' => (string) config('zolta-http.routes.documentation.version', '1.0.0'),
                'description' => (string) config(
                    'zolta-http.routes.documentation.description',
                    'Auto-generated API documentation from Zolta HTTP route attributes.',
                ),
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
                'schemas' => array_replace(
                    [
                        'GenericObject' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                    $schemas,
                ),
            ],
        ];

        $serverUrl = trim((string) config('zolta-http.routes.documentation.server_url', ''), '/');
        if ($serverUrl !== '') {
            $document['servers'] = [[
                'url' => $serverUrl,
                'description' => (string) config(
                    'zolta-http.routes.documentation.server_description',
                    'Application server',
                ),
            ]];
        }

        return $document;
    }

    /**
     * @param  array<int|string,mixed>  $routeArguments
     * @param  array<int,array{class:string,arguments:array<int|string,mixed>}>  $classAttributes
     * @param  array<int,array{class:string,arguments:array<int|string,mixed>}>  $methodAttributes
     * @return array{
     *     operations:list<array{path:string,method:string,operation:array<string,mixed>}>,
     *     schemas:array<string,array<string,mixed>>
     * }
     */
    private static function buildFragment(
        string $controllerClass,
        string $targetMethod,
        array $routeArguments,
        array $classAttributes,
        array $methodAttributes,
        ?string $requestSourceFile = null,
    ): array {
        $route = self::normalizeRouteArguments($routeArguments);
        $routeMetadata = (new RouteMetadataResolver)->resolve($controllerClass, $targetMethod);
        $doc = self::findMergedAttribute($classAttributes, $methodAttributes, DocAttr::class);
        $requestDetails = self::buildRequestDetails(
            requestClass: $routeMetadata->requestClass,
            routePath: $route['path'],
            methods: $route['methods'],
        );

        $uri = self::normalizeRouteUri($route['path'], $route['middleware']);
        $schemas = [];

        if ($routeMetadata->resourceClass !== null && class_exists($routeMetadata->resourceClass)) {
            $schemas[self::shortClassName($routeMetadata->resourceClass)] = self::classToSchema($routeMetadata->resourceClass, true);
        }

        $operations = [];
        foreach ($route['methods'] as $httpMethod) {
            $operation = [
                'operationId' => $route['name']
                    ?? strtolower(str_replace('\\', '.', $controllerClass).'_'.$targetMethod.'_'.$httpMethod),
                'summary' => $doc['arguments']['summary'] ?? self::humanizeOperationName($controllerClass, $targetMethod),
                'description' => $doc['arguments']['description'] ?? '',
                'tags' => array_values(array_filter((array) ($doc['arguments']['tags'] ?? []))),
                'parameters' => self::parametersForMethod(
                    pathParameters: $requestDetails['path_parameters'],
                    queryParameters: $requestDetails['query_parameters'],
                    httpMethod: $httpMethod,
                ),
                'responses' => self::buildResponses(
                    status: $routeMetadata->status,
                    successMessage: $routeMetadata->message,
                    resourceClass: $routeMetadata->resourceClass,
                    authenticated: self::isAuthenticatedRoute($route['middleware']),
                    hasInput: $requestDetails['has_input'],
                ),
            ];

            if ($requestDetails['request_body'] !== null) {
                $operation['requestBody'] = $requestDetails['request_body'];
            }

            if (self::isAuthenticatedRoute($route['middleware'])) {
                $operation['security'] = [['bearerAuth' => []]];
            }

            if ($route['name'] !== null) {
                $operation['x-route-name'] = $route['name'];
            }

            $operations[] = [
                'path' => $uri,
                'method' => strtolower($httpMethod),
                'operation' => $operation,
            ];
        }

        return [
            'operations' => $operations,
            'schemas' => $schemas,
        ];
    }

    /**
     * @param  list<string>  $methods
     * @return array{
     *     path_parameters:list<array<string,mixed>>,
     *     query_parameters:list<array<string,mixed>>,
     *     request_body:?array<string,mixed>,
     *     has_input:bool
     * }
     */
    private static function buildRequestDetails(?string $requestClass, string $routePath, array $methods): array
    {
        $pathPlaceholders = self::pathPlaceholders($routePath);

        if ($requestClass === null || ! class_exists($requestClass)) {
            return [
                'path_parameters' => self::defaultPathParameters($pathPlaceholders),
                'query_parameters' => [],
                'request_body' => null,
                'has_input' => $pathPlaceholders !== [],
            ];
        }

        $request = self::instantiateRequestDescriptor($requestClass);
        $rules = self::normalizeRules(self::callRequestMethod($request, 'rules', []));
        $queryConfig = (array) self::callRequestMethod($request, 'queryParams', []);
        $routeConfig = (array) self::callRequestMethod($request, 'routeParams', []);
        $derivedFields = self::detectDerivedFields($requestClass);

        $pathParameters = self::resolvePathParameters($pathPlaceholders, $routeConfig);
        $queryParameters = self::resolveQueryParameters(
            queryConfig: $queryConfig,
            rules: $rules,
            derivedFields: $derivedFields,
            pathParameters: $pathParameters,
            methods: $methods,
        );

        $requestBody = self::resolveRequestBody(
            rules: $rules,
            derivedFields: $derivedFields,
            pathParameters: $pathParameters,
            queryParameters: $queryParameters,
            methods: $methods,
        );

        return [
            'path_parameters' => $pathParameters,
            'query_parameters' => $queryParameters,
            'request_body' => $requestBody,
            'has_input' => $pathParameters !== [] || $queryParameters !== [] || $requestBody !== null,
        ];
    }

    private static function instantiateRequestDescriptor(string $requestClass): ?object
    {
        try {
            $reflectionClass = new ReflectionClass($requestClass);

            if (($constructor = $reflectionClass->getConstructor()) === null || $constructor->getNumberOfRequiredParameters() === 0) {
                return $reflectionClass->newInstance();
            }

            return $reflectionClass->newInstanceWithoutConstructor();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function callRequestMethod(?object $request, string $method, mixed $default): mixed
    {
        if ($request === null || ! method_exists($request, $method)) {
            return $default;
        }

        try {
            return $request->{$method}();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $routeConfig
     * @return list<array<string,mixed>>
     */
    private static function resolvePathParameters(array $pathPlaceholders, array $routeConfig): array
    {
        $parameters = [];
        foreach ($pathPlaceholders as $pathPlaceholder) {
            $config = (array) ($routeConfig[$pathPlaceholder] ?? []);
            $parameters[] = [
                'name' => $pathPlaceholder,
                'in' => 'path',
                'required' => true,
                'schema' => self::schemaFromParameterConfig($config, ['type' => 'string']),
            ];
        }

        return $parameters;
    }

    /**
     * @param  array<string,array<string,mixed>>  $queryConfig
     * @param  array<string,array<int,string>>  $rules
     * @param  array<string,list<string>>  $derivedFields
     * @param  list<array<string,mixed>>  $pathParameters
     * @param  list<string>  $methods
     * @return list<array<string,mixed>>
     */
    private static function resolveQueryParameters(
        array $queryConfig,
        array $rules,
        array $derivedFields,
        array $pathParameters,
        array $methods,
    ): array {
        $parameters = [];
        $pathNames = array_map(static fn (array $parameter): string => (string) ($parameter['name'] ?? ''), $pathParameters);

        if ($queryConfig !== []) {
            foreach ($queryConfig as $name => $config) {
                $parameters[] = [
                    'name' => (string) $name,
                    'in' => 'query',
                    'required' => (bool) ($config['required'] ?? false),
                    'schema' => self::schemaFromParameterConfig((array) $config, ['type' => 'string']),
                ];
            }

            return $parameters;
        }

        if (! self::containsOnlySafeMethods($methods)) {
            return [];
        }

        $inputMappedFields = $derivedFields['input'];
        foreach ($rules as $field => $ruleSet) {
            if (! self::shouldInferQueryParameter($field, $ruleSet, $pathNames, $derivedFields, $inputMappedFields)) {
                continue;
            }

            $schema = self::schemaFromRuleSet($field, $rules);
            $parameters[] = [
                'name' => $field,
                'in' => 'query',
                'required' => self::isRequiredRule($ruleSet),
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    /**
     * @param  array<string,array<int,string>>  $rules
     * @param  array<string,list<string>>  $derivedFields
     * @param  list<array<string,mixed>>  $pathParameters
     * @param  list<array<string,mixed>>  $queryParameters
     * @param  list<string>  $methods
     * @return array<string,mixed>|null
     */
    private static function resolveRequestBody(
        array $rules,
        array $derivedFields,
        array $pathParameters,
        array $queryParameters,
        array $methods,
    ): ?array {
        if (self::containsOnlySafeMethods($methods)) {
            return null;
        }

        $excluded = array_merge(
            array_map(static fn (array $parameter): string => (string) ($parameter['name'] ?? ''), $pathParameters),
            array_map(static fn (array $parameter): string => (string) ($parameter['name'] ?? ''), $queryParameters),
            $derivedFields['route'],
            $derivedFields['user'],
            self::reservedInternalFields()
        );
        $excluded = array_values(array_unique(array_filter($excluded)));

        $properties = [];
        $required = [];
        foreach ($rules as $field => $ruleSet) {
            if (! self::shouldIncludeRequestBodyField($field, $excluded)) {
                continue;
            }

            $properties[$field] = self::schemaFromRuleSet($field, $rules);
            if (self::isRequiredRule($ruleSet)) {
                $required[] = $field;
            }
        }

        if ($properties === []) {
            return null;
        }

        $contentType = self::containsBinarySchema($properties)
            ? 'multipart/form-data'
            : 'application/json';

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = array_values(array_unique($required));
        }

        return [
            'required' => $required !== [],
            'content' => [
                $contentType => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $pathParameters
     * @param  list<array<string,mixed>>  $queryParameters
     * @return list<array<string,mixed>>
     */
    private static function parametersForMethod(array $pathParameters, array $queryParameters, string $httpMethod): array
    {
        $parameters = $pathParameters;

        if (self::isSafeMethod($httpMethod)) {
            $parameters = array_merge($parameters, $queryParameters);
        }

        return $parameters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildResponses(
        int $status,
        string $successMessage,
        ?string $resourceClass,
        bool $authenticated,
        bool $hasInput,
    ): array {
        $dataSchema = ['type' => 'object', 'additionalProperties' => true];
        if ($resourceClass !== null && class_exists($resourceClass)) {
            $dataSchema = ['$ref' => '#/components/schemas/'.self::shortClassName($resourceClass)];
        }

        $responses = [
            (string) $status => [
                'description' => $successMessage,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['success', 'message', 'data', 'errors'],
                            'properties' => [
                                'success' => ['type' => 'boolean', 'example' => true],
                                'message' => ['type' => 'string', 'example' => $successMessage],
                                'data' => $dataSchema,
                                'errors' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($authenticated) {
            $responses['401'] = self::errorResponse('Unauthorized request.');
        }

        if ($hasInput) {
            $responses['422'] = self::errorResponse('Validation failed.');
        }

        return $responses;
    }

    /**
     * @return array<string,mixed>
     */
    private static function errorResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['success', 'message', 'data', 'errors'],
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => false],
                            'message' => ['type' => 'string', 'example' => $description],
                            'data' => ['type' => 'object', 'additionalProperties' => true],
                            'errors' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string,array<int,string>>  $rules
     * @return array<string,mixed>
     */
    private static function schemaFromRuleSet(string $field, array $rules): array
    {
        $ruleSet = $rules[$field] ?? [];
        $schema = ['type' => 'string'];

        foreach ($ruleSet as $rule) {
            $schema = self::applyRuleToSchema($schema, $rule);
        }

        $wildcardKey = $field.'.*';
        if (($schema['type'] ?? null) === 'array' && isset($rules[$wildcardKey])) {
            $itemSchema = ['type' => 'string'];
            foreach ($rules[$wildcardKey] as $rule) {
                $itemSchema = self::applyRuleToSchema($itemSchema, $rule);
            }
            $schema['items'] = $itemSchema;
        }

        if (($schema['type'] ?? null) === 'array' && ! isset($schema['items'])) {
            $schema['items'] = ['type' => 'string'];
        }

        return $schema;
    }

    /**
     * @return array<string,mixed>
     */
    private static function applyRuleToSchema(array $schema, string $rule): array
    {
        $name = $rule;
        $parameters = '';
        if (str_contains($rule, ':')) {
            [$name, $parameters] = explode(':', $rule, 2);
        }

        $name = strtolower(trim($name));

        return match ($name) {
            'array' => array_replace($schema, ['type' => 'array']),
            'boolean', 'bool' => ['type' => 'boolean'],
            'integer', 'int' => ['type' => 'integer'],
            'numeric', 'decimal', 'float', 'double' => ['type' => 'number'],
            'object' => ['type' => 'object', 'additionalProperties' => true],
            'uuid' => array_replace($schema, ['type' => 'string', 'format' => 'uuid']),
            'email' => array_replace($schema, ['type' => 'string', 'format' => 'email']),
            'date' => array_replace($schema, ['type' => 'string', 'format' => 'date']),
            'date_format', 'datetime', 'date_time' => array_replace($schema, ['type' => 'string', 'format' => 'date-time']),
            'file', 'image' => ['type' => 'string', 'format' => 'binary'],
            'min' => self::applyNumericOrLengthConstraint($schema, 'min', $parameters),
            'max' => self::applyNumericOrLengthConstraint($schema, 'max', $parameters),
            'in' => self::applyEnumConstraint($schema, $parameters),
            default => $schema,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private static function applyNumericOrLengthConstraint(array $schema, string $rule, string $parameters): array
    {
        if (! is_numeric($parameters)) {
            return $schema;
        }

        $value = str_contains($parameters, '.')
            ? (float) $parameters
            : (int) $parameters;

        if (($schema['type'] ?? null) === 'integer' || ($schema['type'] ?? null) === 'number') {
            $schema[$rule === 'min' ? 'minimum' : 'maximum'] = $value;

            return $schema;
        }

        $schema[$rule === 'min' ? 'minLength' : 'maxLength'] = (int) $value;

        return $schema;
    }

    /**
     * @return array<string,mixed>
     */
    private static function applyEnumConstraint(array $schema, string $parameters): array
    {
        if ($parameters === '') {
            return $schema;
        }

        $schema['enum'] = array_values(array_filter(array_map(trim(...), explode(',', $parameters)), static fn (string $value): bool => $value !== ''));

        return $schema;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $fallback
     * @return array<string,mixed>
     */
    private static function schemaFromParameterConfig(array $config, array $fallback): array
    {
        $type = strtolower((string) ($config['type'] ?? $fallback['type'] ?? 'string'));

        $schema = match ($type) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double', 'number', 'numeric' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'object' => ['type' => 'object', 'additionalProperties' => true],
            default => ['type' => 'string'],
        };

        if (isset($config['format']) && is_string($config['format'])) {
            $schema['format'] = $config['format'];
        }

        return $schema;
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    private static function containsBinarySchema(array $properties): bool
    {
        foreach ($properties as $property) {
            if (($property['format'] ?? null) === 'binary') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,list<string>>
     */
    private static function detectDerivedFields(string $requestClass): array
    {
        $derived = [
            'route' => [],
            'user' => [],
            'input' => [],
        ];

        try {
            $reflectionClass = new ReflectionClass($requestClass);
            $file = $reflectionClass->getFileName();
            if ($file === false || ! is_file($file)) {
                return $derived;
            }

            $lines = file($file);
            if (! is_array($lines) || $lines === []) {
                return $derived;
            }

            $source = implode('', array_slice(
                $lines,
                max(0, $reflectionClass->getStartLine() - 1),
                max(0, $reflectionClass->getEndLine() - $reflectionClass->getStartLine() + 1),
            ));

            preg_match_all('/[\'"](?P<key>[^\'"]+)[\'"]\s*=>\s*(?P<expr>[^,\n]+(?:\n(?!\s*[\'"]).*)*)/m', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $key = trim((string) $match['key']);
                $expression = (string) $match['expr'];
                if ($key === '') {
                    continue;
                }

                if (str_contains($expression, '$this->route(') || str_contains($expression, '$this->route()?->')) {
                    $derived['route'][] = $key;
                }

                if (str_contains($expression, '$this->user()') || str_contains($expression, 'identity()?->')) {
                    $derived['user'][] = $key;
                }

                if (str_contains($expression, '$this->input(') || str_contains($expression, '$this->query(')) {
                    $derived['input'][] = $key;
                }
            }
        } catch (\Throwable) {
            return $derived;
        }

        $derived['route'] = array_values(array_unique($derived['route']));
        $derived['user'] = array_values(array_unique($derived['user']));
        $derived['input'] = array_values(array_unique($derived['input']));

        return $derived;
    }

    /**
     * @param  array<string,mixed>  $rules
     * @return array<string,array<int,string>>
     */
    private static function normalizeRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $field => $ruleDefinition) {
            $field = (string) $field;

            if (is_string($ruleDefinition)) {
                $parts = explode('|', $ruleDefinition);
            } elseif (is_array($ruleDefinition)) {
                $parts = array_map(self::normalizeRuleToken(...), $ruleDefinition);
            } else {
                $parts = [self::normalizeRuleToken($ruleDefinition)];
            }

            $normalized[$field] = array_values(array_filter(array_map(trim(...), $parts), static fn (string $rule): bool => $rule !== ''));
        }

        return $normalized;
    }

    private static function normalizeRuleToken(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if ($rule instanceof \Stringable) {
            return (string) $rule;
        }

        if (is_object($rule) && method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        return '';
    }

    /**
     * @param  list<string>  $methods
     */
    private static function containsOnlySafeMethods(array $methods): bool
    {
        foreach ($methods as $method) {
            if (! self::isSafeMethod($method)) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'DELETE'], true);
    }

    /**
     * @param  list<string>  $pathNames
     * @param  array<string,list<string>>  $derivedFields
     * @param  list<string>  $inputMappedFields
     */
    private static function shouldInferQueryParameter(
        string $field,
        array $ruleSet,
        array $pathNames,
        array $derivedFields,
        array $inputMappedFields,
    ): bool {
        if (str_contains($field, '.')) {
            return false;
        }

        if (in_array($field, $pathNames, true)) {
            return false;
        }

        if (in_array($field, $derivedFields['route'], true) || in_array($field, $derivedFields['user'], true)) {
            return false;
        }

        if (in_array($field, self::reservedInternalFields(), true)) {
            return false;
        }

        if ($inputMappedFields !== [] && ! in_array($field, $inputMappedFields, true)) {
            return false;
        }

        return ! in_array('file', $ruleSet, true) && ! in_array('image', $ruleSet, true);
    }

    /**
     * @param  list<string>  $excluded
     */
    private static function shouldIncludeRequestBodyField(string $field, array $excluded): bool
    {
        if (str_contains($field, '.')) {
            return false;
        }

        return ! in_array($field, $excluded, true);
    }

    /**
     * @return list<string>
     */
    private static function reservedInternalFields(): array
    {
        return ['options', 'route_params', 'endpoint'];
    }

    /**
     * @param  array<int,string>  $ruleSet
     */
    private static function isRequiredRule(array $ruleSet): bool
    {
        return in_array('required', array_map(static fn (string $rule): string => strtolower(strtok($rule, ':') ?: $rule), $ruleSet), true);
    }

    /**
     * @return list<string>
     */
    private static function pathPlaceholders(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_values(array_unique(array_map(
            static fn (string $placeholder): string => trim($placeholder, '?'),
            $matches[1],
        )));
    }

    /**
     * @param  list<string>  $placeholders
     * @return list<array<string,mixed>>
     */
    private static function defaultPathParameters(array $placeholders): array
    {
        return array_map(static fn (string $placeholder): array => [
            'name' => $placeholder,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ], $placeholders);
    }

    /**
     * @param  array<int|string,mixed>  $args
     * @return array{
     *     path:string,
     *     methods:list<string>,
     *     middleware:list<string>,
     *     name:?string
     * }
     */
    private static function normalizeRouteArguments(array $args): array
    {
        $path = (string) self::argument($args, ['path'], $args[0] ?? '');
        $methods = array_values(array_map(strtoupper(...), (array) self::argument($args, ['methods'], $args[1] ?? ['GET'])));

        $middleware = self::argument($args, ['middleware', 'middlewares'], $args[2] ?? []);
        if (! is_array($middleware)) {
            $middleware = $middleware !== null ? [(string) $middleware] : [];
        }

        $auth = self::argument($args, ['auth'], null);
        if ($auth !== null) {
            $authMiddleware = match (true) {
                $auth === true => 'auth:sanctum',
                is_string($auth) && ! str_starts_with($auth, 'auth:') => 'auth:'.$auth,
                is_string($auth) => $auth,
                default => null,
            };

            if ($authMiddleware !== null && ! in_array($authMiddleware, $middleware, true)) {
                $middleware[] = $authMiddleware;
            }
        }

        $middleware = array_values(array_unique(array_map(
            static fn (string $value): string => $value === 'auth' ? 'auth:sanctum' : $value,
            array_map(strval(...), $middleware),
        )));

        return [
            'path' => $path,
            'methods' => $methods,
            'middleware' => $middleware,
            'name' => ($name = self::argument($args, ['name'], $args[3] ?? null)) !== null ? (string) $name : null,
        ];
    }

    private static function normalizeRouteUri(string $path, array $middleware): string
    {
        $segments = [];
        if (in_array('api', $middleware, true)) {
            $segments[] = 'api';
        }
        $segments[] = trim($path, '/');

        return '/'.trim(implode('/', array_filter($segments, static fn (?string $segment): bool => $segment !== null && $segment !== '')), '/');
    }

    private static function isAuthenticatedRoute(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            $value = strtolower((string) $entry);
            if ($value === 'auth' || str_starts_with($value, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,array{class:string,arguments:array<int|string,mixed>}>  $classAttributes
     * @param  array<int,array{class:string,arguments:array<int|string,mixed>}>  $methodAttributes
     * @return array{class:string,arguments:array<int|string,mixed>}|null
     */
    private static function findMergedAttribute(array $classAttributes, array $methodAttributes, string $target): ?array
    {
        foreach ($methodAttributes as $methodAttribute) {
            if ($methodAttribute['class'] === $target) {
                return $methodAttribute;
            }
        }

        foreach ($classAttributes as $classAttribute) {
            if ($classAttribute['class'] === $target) {
                return $classAttribute;
            }
        }

        return null;
    }

    /**
     * @param  list<array{path:string,method:string,operation:array<string,mixed>}>  $operations
     * @return list<array{path:string,method:string,operation:array<string,mixed>}>
     */
    private static function uniqueOperations(array $operations): array
    {
        $unique = [];
        $seen = [];

        foreach ($operations as $operation) {
            $key = $operation['method'].' '.$operation['path'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $operation;
        }

        return $unique;
    }

    /**
     * @return array<string,mixed>
     */
    private static function classToSchema(string $class, bool $fallbackToGenericObject = false): array
    {
        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (\Throwable) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        $properties = [];
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            $properties[$reflectionProperty->getName()] = self::schemaFromReflectionType($reflectionProperty->getType());
        }

        $constructor = $reflectionClass->getConstructor();
        if ($constructor !== null && $constructor->getDeclaringClass()->getName() === $reflectionClass->getName()) {
            foreach ($constructor->getParameters() as $reflectionParameter) {
                if (isset($properties[$reflectionParameter->getName()])) {
                    continue;
                }

                $properties[$reflectionParameter->getName()] = self::schemaFromReflectionType($reflectionParameter->getType());
            }
        }

        if ($properties === [] && $fallbackToGenericObject) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function schemaFromReflectionType(?\ReflectionType $reflectionType): array
    {
        if (! $reflectionType instanceof ReflectionNamedType) {
            return ['type' => 'object', 'additionalProperties' => true];
        }

        return match (strtolower($reflectionType->getName())) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            default => ['type' => 'object', 'additionalProperties' => true],
        };
    }

    /**
     * @param  array<int|string,mixed>  $args
     * @param  list<string|int>  $candidates
     */
    private static function argument(array $args, array $candidates, mixed $default): mixed
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $args)) {
                return $args[$candidate];
            }

            $lower = strtolower((string) $candidate);
            foreach ($args as $key => $value) {
                if (is_string($key) && strtolower($key) === $lower) {
                    return $value;
                }
            }
        }

        return $default;
    }

    private static function shortClassName(string $class): string
    {
        $class = trim($class, '\\');

        return str_contains($class, '\\')
            ? (string) substr($class, (int) strrpos($class, '\\') + 1)
            : $class;
    }

    private static function humanizeOperationName(string $controllerClass, string $method): string
    {
        $name = self::shortClassName($controllerClass);
        if ($method !== '__invoke') {
            $name .= ' '.$method;
        }

        $name = preg_replace('/Controller$/', '', $name) ?? $name;
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name) ?? $name;

        return trim($name);
    }
}
