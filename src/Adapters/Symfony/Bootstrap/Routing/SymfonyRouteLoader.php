<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Routing;

use LogicException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Zolta\Http\Router\Attributes\Route as RouteAttribute;
use Zolta\Http\Router\Cache\ReflectionCache;
use Zolta\Http\Symfony\Bootstrap\Discovery\MapScanner;

/**
 * Symfony route loader scaffold for Zolta attribute routes.
 */
final class SymfonyRouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param  array<int,string>|null  $paths
     */
    public function __construct(private readonly ?string $projectDir = null, private readonly ?array $paths = null)
    {
        if (! self::isAvailable()) {
            throw new LogicException('Symfony Routing component is required for SymfonyRouteLoader');
        }
    }

    /**
     * Standard Loader entry point (resource is ignored; directories taken from project dir).
     *
     * @param  mixed  $resource
     */
    public function load($resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new LogicException('SymfonyRouteLoader has already been loaded.');
        }

        $this->loaded = true;

        $dirs = is_array($resource) ? $resource : null;

        return $this->buildCollection($dirs);
    }

    /**
     * Allow direct invocation (legacy signature).
     *
     * @param  array<int,string>|null  $directories
     */
    public function buildCollection(?array $directories = null): RouteCollection
    {
        $routeCollection = new RouteCollection;
        $dirs = $directories ?? ($this->paths ?? [$this->projectDir ? $this->projectDir.'/src' : getcwd().'/src']);
        $files = MapScanner::files($dirs);

        foreach ($files as $file) {
            $fqcn = MapScanner::parseClassFromFile($file);
            if ($fqcn === null) {
                continue;
            }

            if (! class_exists($fqcn, false)) {
                require_once $file;
            }

            if (! class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            foreach ($this->extractClassRoutes($ref) as $route) {
                $routeCollection->add($route['name'], $route['route']);
            }
            foreach ($ref->getMethods() as $method) {
                foreach ($this->extractMethodRoutes($ref, $method) as $route) {
                    $routeCollection->add($route['name'], $route['route']);
                }
            }
        }

        return $routeCollection;
    }

    public static function isAvailable(): bool
    {
        return class_exists(RouteCollection::class);
    }

    public function supports($resource, ?string $type = null): bool
    {
        return $type === 'zolta';
    }

    /**
     * @param  \ReflectionClass<object>  $reflectionClass
     * @return array<int,array{name:string,route:Route}>
     */
    private function extractClassRoutes(\ReflectionClass $reflectionClass): array
    {
        $routes = [];
        $attrs = ReflectionCache::getClassAttributes($reflectionClass->getName());

        foreach ($attrs as $attr) {
            if ($attr['class'] !== RouteAttribute::class) {
                continue;
            }

            $inst = $attr['instance'] ?? $this->makeRouteAttribute($attr['arguments'] ?? []);
            $route = $this->buildRoute($inst, $reflectionClass->getName(), '__invoke');
            $routes[] = $route;
        }

        return $routes;
    }

    /**
     * @param  \ReflectionClass<object>  $reflectionClass
     * @return array<int,array{name:string,route:Route}>
     */
    private function extractMethodRoutes(\ReflectionClass $reflectionClass, \ReflectionMethod $reflectionMethod): array
    {
        $routes = [];
        $attrs = ReflectionCache::getMethodAttributes($reflectionClass->getName(), $reflectionMethod->getName());

        foreach ($attrs as $attr) {
            if ($attr['class'] !== RouteAttribute::class) {
                continue;
            }

            $inst = $attr['instance'] ?? $this->makeRouteAttribute($attr['arguments'] ?? []);
            $routes[] = $this->buildRoute($inst, $reflectionClass->getName(), $reflectionMethod->getName());
        }

        return $routes;
    }

    /**
     * @return array{name:string,route:Route}
     */
    private function buildRoute(RouteAttribute $routeAttribute, string $class, string $method): array
    {
        $methods = $routeAttribute->methods ?: ['GET'];
        $name = $routeAttribute->name ?? $this->routeName($class, $method);
        $middleware = (array) $routeAttribute->middleware;
        $auth = $routeAttribute->auth;
        $authorized = $routeAttribute->authorized ?? [];

        if ($auth !== null) {
            $authMiddleware = $auth === true ? 'auth' : (string) $auth;
            if ($authMiddleware !== '' && ! in_array($authMiddleware, $middleware, true)) {
                $middleware[] = $authMiddleware;
            }
        }

        $middleware = array_values(array_unique(array_filter(
            array_map(strval(...), $middleware),
            static fn (string $v): bool => $v !== ''
        )));

        $effectivePrefix = $routeAttribute->prefix;
        if (in_array('web', $middleware, true) && ! in_array('api', $middleware, true)) {
            $effectivePrefix = '';
        }
        if ($effectivePrefix === null) {
            $effectivePrefix = in_array('api', $middleware, true) ? 'api' : '';
        }

        $path = $this->normalizePath($effectivePrefix, $routeAttribute->path);

        $route = new Route($path, [
            'target' => $class,
            'target_method' => $method,
            '_controller' => SymfonyProxyController::class.'::__invoke',
            'middleware' => $middleware,
            'authorized' => $authorized,
        ]);
        $route->setMethods($methods);

        return ['name' => $name, 'route' => $route];
    }

    private function normalizePath(?string $prefix, string $path): string
    {
        $prefix ??= '';
        $full = trim($prefix.'/'.ltrim($path, '/'), '/');

        return '/'.$full;
    }

    private function routeName(string $class, string $method): string
    {
        return strtolower(str_replace('\\', '.', $class)).'.'.$method;
    }

    /**
     * @param  array<int|string,mixed>  $args
     */
    private function makeRouteAttribute(array $args): RouteAttribute
    {
        $path = $args['path'] ?? ($args[0] ?? '');
        $methods = $args['methods'] ?? ($args[1] ?? ['GET']);
        $prefix = $args['prefix'] ?? ($args[2] ?? 'api');
        $middleware = $args['middleware'] ?? ($args[3] ?? []);
        $auth = $args['auth'] ?? ($args[4] ?? null);
        $authorized = $args['authorized'] ?? ($args[5] ?? []);
        $name = $args['name'] ?? ($args[6] ?? null);

        return new RouteAttribute($path, $methods, $prefix, $middleware, $auth, $authorized, $name);
    }
}
