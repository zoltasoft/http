<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Zolta\Http\Router\Attributes\Route as RouteAttribute;
use Zolta\Http\Router\Cache\ReflectionCache;

/**
 * Scans application source paths for attribute-based controllers and
 * produces the Laravel route definitions needed to register them.
 */
final class AttributeRouteLoader
{
    /**
     * @param  callable(string):void|null  $onFile
     * @param  callable(string):void|null  $onClass
     * @param  callable(string,array<int,array{code:string,middleware:list<string>}>):void|null  $onRoutesForFile
     * @return list<array{code:string,middleware:list<string>}>
     */
    public static function load(string $basePath, ?callable $onFile = null, ?callable $onClass = null, ?callable $onRoutesForFile = null): array
    {
        $routes = [];
        $finder = new Finder;
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if ($realPath === false || ! self::shouldProcessFile($realPath)) {
                continue;
            }

            if ($onFile !== null) {
                $onFile($realPath);
            }

            $fqcn = self::fileToClass($realPath);
            if (! $fqcn) {
                continue;
            }

            // Ensure the class file is loaded so attributes are available for reflection
            if (! class_exists($fqcn)) {
                require_once $realPath;
            }

            if (! class_exists($fqcn)) {
                continue;
            }

            if ($onClass !== null) {
                $onClass($fqcn);
            }

            $refClass = new ReflectionClass($fqcn);

            $routesForFile = self::uniqueRoutes(array_merge(
                self::extractClassRoutes($refClass),
                self::extractMethodRoutes($refClass)
            ));

            $routes = array_merge($routes, $routesForFile);
            if ($onRoutesForFile !== null) {
                $onRoutesForFile($realPath, $routesForFile);
            }
        }

        return RouteRegistrationSorter::sort(self::uniqueRoutes($routes));
    }

    /**
     * Load attribute routes from a single file without scanning directories.
     *
     * @param  callable(string):void|null  $onClass
     * @return list<array{code:string,middleware:list<string>}>
     */
    public static function loadFile(string $filePath, ?callable $onClass = null): array
    {
        $realPath = realpath($filePath) ?: $filePath;
        if (! is_file($realPath) || ! self::shouldProcessFile($realPath)) {
            return [];
        }

        $fqcn = self::fileToClass($realPath);
        if (! $fqcn) {
            return [];
        }

        if (! class_exists($fqcn)) {
            require_once $realPath;
        }

        if (! class_exists($fqcn)) {
            return [];
        }

        if ($onClass !== null) {
            $onClass($fqcn);
        }

        $reflectionClass = new ReflectionClass($fqcn);

        return self::uniqueRoutes(array_merge(
            self::extractClassRoutes($reflectionClass),
            self::extractMethodRoutes($reflectionClass)
        ));
    }

    private static function fileToClass(string $filePath): ?string
    {
        $info = self::parseClassFromFile($filePath);
        if ($info === null) {
            return null;
        }

        [$namespace, $class] = $info;
        if ($namespace === null) {
            $relative = Str::after(str_replace('\\', '/', $filePath), str_replace('\\', '/', app_path()) . '/');
            $segments = array_filter(explode('/', $relative));
            array_pop($segments);
            $namespace = 'App' . ($segments !== [] ? '\\' . implode('\\', $segments) : '');
        }

        return trim($namespace . '\\' . $class, '\\');
    }

    /**
     * @return array{0:string|null,1:string}|null
     */
    private static function parseClassFromFile(string $filePath): ?array
    {
        $source = @file_get_contents($filePath);
        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = null;
        $class = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                $i++;
                while ($i < $count) {
                    $part = $tokens[$i];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], self::namespaceTokenTypes(), true)) {
                        $namespace .= $part[1];
                    }
                    $i++;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_CLASS && self::isNamedClass($tokens, $i)) {
                $i++;
                while ($i < $count) {
                    $nameToken = $tokens[$i];
                    if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                        $class = $nameToken[1];
                        break 2;
                    }
                    $i++;
                }
            }
        }

        if ($class === null) {
            return null;
        }

        return [$namespace, $class];
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private static function isNamedClass(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && in_array($token[0], [T_NEW, T_DOUBLE_COLON], true)) {
                return false;
            }
            break;
        }

        return true;
    }

    /**
     * @return list<int>
     */
    private static function namespaceTokenTypes(): array
    {
        static $cache;
        if ($cache !== null) {
            return $cache;
        }

        $tokens = [T_STRING];
        if (defined('T_NAME_QUALIFIED')) {
            $tokens[] = T_NAME_QUALIFIED;
        }
        if (defined('T_NAME_FULLY_QUALIFIED')) {
            $tokens[] = T_NAME_FULLY_QUALIFIED;
        }
        $tokens[] = T_NS_SEPARATOR;

        return $cache = $tokens;
    }

    // ===============================================================
    // ROUTE EXTRACTION
    // ===============================================================

    /**
     * @param  ReflectionClass<object>  $reflectionClass
     * @return list<array{code:string,middleware:list<string>}>
     */
    private static function extractClassRoutes(ReflectionClass $reflectionClass): array
    {
        $output = [];
        $attrs = ReflectionCache::getClassAttributes($reflectionClass->getName());

        foreach ($attrs as $attr) {
            if ($attr['class'] !== RouteAttribute::class) {
                continue;
            }

            [$path, $methods, $middleware, $name, $authorized] = self::normalizeRouteArguments($attr['arguments']);

            $uri = '/' . trim((string) $path, '/');
            $methodsStr = self::phpArray($methods);
            $middlewareStr = self::phpArray($middleware);
            $nameStr = var_export($name ?? self::routeName($reflectionClass->getName()), true);

            $authorizedDefaults = $authorized !== [] ? "\n    ->defaults('authorized', " . self::phpArray($authorized) . ')' : '';

            $code = <<<PHP
Route::match({$methodsStr}, '{$uri}', [\Zolta\Http\Router\Laravel\Bootstrap\AutoInvokeProxyController::class, '__invoke'])
    ->defaults('target', {$reflectionClass->getName()}::class)
    ->defaults('target_method', '__invoke'){$authorizedDefaults}
    ->middleware({$middlewareStr})
    ->name({$nameStr});
PHP;

            $output[] = [
                'code' => $code,
                'middleware' => $middleware,
            ];
        }

        return $output;
    }

    /**
     * @param  ReflectionClass<object>  $reflectionClass
     * @return list<array{code:string,middleware:list<string>}>
     */
    private static function extractMethodRoutes(ReflectionClass $reflectionClass): array
    {
        $output = [];

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $attrs = ReflectionCache::getMethodAttributes($reflectionClass->getName(), $reflectionMethod->getName());
            foreach ($attrs as $attr) {
                if ($attr['class'] !== RouteAttribute::class) {
                    continue;
                }

                [$path, $methods, $middleware, $name, $authorized] = self::normalizeRouteArguments($attr['arguments']);

                $uri = '/' . trim((string) $path, '/');
                $methodsStr = self::phpArray($methods);
                $middlewareStr = self::phpArray($middleware);
                $nameStr = var_export($name ?? self::routeName($reflectionClass->getName() . '.' . $reflectionMethod->getName()), true);

                $authorizedDefaults = $authorized !== [] ? "\n    ->defaults('authorized', " . self::phpArray($authorized) . ')' : '';

                $code = <<<PHP
Route::match({$methodsStr}, '{$uri}', [\\Zolta\\Http\\Router\\Laravel\\Bootstrap\\AutoInvokeProxyController::class, '__invoke'])
    ->defaults('target', {$reflectionClass->getName()}::class)
    ->defaults('target_method', '{$reflectionMethod->getName()}'){$authorizedDefaults}
    ->middleware({$middlewareStr})
    ->name({$nameStr});
PHP;

                $output[] = [
                    'code' => $code,
                    'middleware' => $middleware,
                ];
            }
        }

        return $output;
    }

    // ===============================================================
    // ARGUMENT NORMALIZATION
    // ===============================================================

    /**
     * @param  array<int|string, mixed>  $args
     * @return array{0:mixed,1:array<int,string>|string,2:list<string>,3:string|null,4:array<int|string,string>}
     */
    private static function normalizeRouteArguments(array $args): array
    {
        $path = self::argument($args, ['path'], $args[0] ?? '');
        $methods = (array) self::argument($args, ['methods'], $args[1] ?? ['GET']);

        $middleware = self::argument($args, ['middleware', 'middlewares'], $args[2] ?? []);
        if (! is_array($middleware)) {
            $middleware = $middleware !== null ? [(string) $middleware] : [];
        }

        // Semantic `auth` flag (true | 'sanctum' | 'web' | 'api')
        $auth = self::argument($args, ['auth'], null);
        if ($auth !== null) {
            $authMiddleware = match (true) {
                $auth === true => 'auth:sanctum',
                is_string($auth) && ! str_starts_with($auth, 'auth:') => "auth:$auth",
                is_string($auth) => $auth,
                default => null,
            };

            if ($authMiddleware && ! in_array($authMiddleware, $middleware, true)) {
                $middleware[] = $authMiddleware;
            }
        }

        $name = self::argument($args, ['name'], $args[3] ?? null);

        // Authorization metadata
        $authorized = self::argument($args, ['authorized'], []);

        $middleware = array_values(array_unique(array_filter(
            array_map(
                static fn(string $mw): string =>
                // Normalize generic auth to the Sanctum guard used for APIs.
                $mw === 'auth' ? 'auth:sanctum' : $mw,
                array_map(strval(...), $middleware)
            ),
            static fn(string $v): bool => $v !== ''
        )));

        return [$path, $methods, $middleware, $name, (array) $authorized];
    }

    // ===============================================================
    // HELPERS
    // ===============================================================

    /**
     * @param  array<int|string, mixed>|string|null  $value
     */
    private static function phpArray(array|string|null $value): string
    {
        return var_export((array) $value, true);
    }

    private static function routeName(string $fqcn): string
    {
        return strtolower(str_replace('\\', '.', $fqcn));
    }

    /**
     * @param  array<int|string, mixed>  $args
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

    private static function shouldProcessFile(string $filePath): bool
    {
        $appPath = str_replace('\\', '/', app_path());
        $normalized = str_replace('\\', '/', $filePath);

        if (! Str::startsWith($normalized, $appPath . '/')) {
            return false;
        }

        $relative = Str::after($normalized, $appPath . '/');
        foreach (self::excludeRegexes() as $regex) {
            if (preg_match($regex, $relative) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function excludeRegexes(): array
    {
        static $regexes;
        if ($regexes !== null) {
            return $regexes;
        }

        $patterns = config('zolta.options.exclude_paths', config('zolta-http.routes.exclude_paths', []));
        $regexes = [];

        foreach ((array) $patterns as $pattern) {
            $regex = self::globToRegex($pattern);
            if ($regex !== null) {
                $regexes[] = $regex;
            }
        }

        return $regexes;
    }

    private static function globToRegex(string $pattern): ?string
    {
        if ($pattern === '') {
            return null;
        }

        $pattern = str_replace('\\', '/', $pattern);
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace(['\*\*', '\*', '\?'], ['.*', '[^/]*', '.'], $pattern);

        return '/^' . $pattern . '$/i';
    }

    /**
     * @param  list<array{code:string,middleware:list<string>}>  $routes
     * @return list<array{code:string,middleware:list<string>}>
     */
    private static function uniqueRoutes(array $routes): array
    {
        $seen = [];
        $unique = [];

        foreach ($routes as $route) {
            $key = $route['code'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $route;
        }

        return $unique;
    }
}
