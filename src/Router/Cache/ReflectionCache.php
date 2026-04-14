<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Cache;

use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use ReflectionParameter;

final class ReflectionCache
{
    /** @var array<string,array<int,array{name:string,typeName:?string,isBuiltin:bool,allowsNull:bool,isOptional:bool,default:mixed,mapKey:?string,attributes:array<int,array{class:string,arguments:array<int|string,mixed>}>,reflectionParam:?ReflectionParameter}>> */
    private static array $ctorParams = [];

    /** @var array<string,array<int,array{class:string,arguments:array<int|string,mixed>}>> */
    private static array $classAttributes = [];

    /** @var array<string,array<int,array{class:string,arguments:array<int|string,mixed>}>> */
    private static array $methodAttributes = [];

    private static ?bool $persistentAvailable = null;

    private static function persistentKeyForMethodAttributes(string $class, string $method): string
    {
        return 'zolta:reflection:methodattrs:'.
            str_replace('\\', '.', ltrim($class, '\\')).
            ':'.$method;
    }

    /**
     * Retrieve attributes for a specific method.
     *
     * @param  class-string  $class
     * @return array<int,array{class:string,arguments:array<int|string,mixed>}>
     */
    public static function getMethodAttributes(string $class, string $method): array
    {
        $key = $class.'::'.$method;

        //  1. Check runtime cache
        if (isset(self::$methodAttributes[$key])) {
            return self::$methodAttributes[$key];
        }

        //  2. Check persistent cache
        $persistentKey = self::persistentKeyForMethodAttributes($class, $method);
        $persisted = self::persistentFetch($persistentKey);
        if ($persisted !== null) {
            return self::$methodAttributes[$key] = $persisted;
        }

        //  3. Build via reflection
        $result = [];
        try {
            $reflectionClass = new ReflectionClass($class);
            if (! $reflectionClass->hasMethod($method)) {
                self::$methodAttributes[$key] = $result;

                return $result;
            }

            $refMethod = $reflectionClass->getMethod($method);
            foreach ($refMethod->getAttributes() as $attribute) {
                $result[] = [
                    'class' => $attribute->getName(),
                    'arguments' => $attribute->getArguments(),
                ];
            }
        } catch (\Throwable) {
            self::$methodAttributes[$key] = [];

            return [];
        }

        //  4. Persist and cache
        self::persistentStore($persistentKey, $result);
        self::$methodAttributes[$key] = $result;

        return $result;
    }

    private static function persistentAvailable(): bool
    {
        if (self::$persistentAvailable !== null) {
            return self::$persistentAvailable;
        }

        if (class_exists(Cache::class)) {
            self::$persistentAvailable = true;

            return true;
        }

        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            self::$persistentAvailable = true;

            return true;
        }

        self::$persistentAvailable = false;

        return false;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private static function persistentFetch(string $key): ?array
    {
        if (! self::persistentAvailable()) {
            return null;
        }

        if (class_exists(Cache::class)) {
            try {
                $v = Cache::get($key);

                return is_array($v) ? $v : null;
            } catch (\Throwable) {
                return null;
            }
        }

        if (function_exists('apcu_fetch')) {
            $ok = false;
            $v = @apcu_fetch($key, $ok);

            return $ok && is_array($v) ? $v : null;
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private static function persistentStore(string $key, array $value): void
    {
        if (! self::persistentAvailable()) {
            return;
        }

        if (class_exists(Cache::class)) {
            try {
                Cache::forever($key, $value);
            } catch (\Throwable) {
            }

            return;
        }

        if (function_exists('apcu_store')) {
            @apcu_store($key, $value);
        }
    }

    private static function persistentKeyForClass(string $class): string
    {
        return 'zolta:reflection:ctor:'.str_replace('\\', '.', ltrim($class, '\\'));
    }

    private static function persistentKeyForClassAttributes(string $class): string
    {
        return 'zolta:reflection:classattrs:'.str_replace('\\', '.', ltrim($class, '\\'));
    }

    /**
     * Returns metadata for a class constructor parameters.
     *
     * @param  class-string  $class
     * @return array<int,array{name:string,typeName:?string,isBuiltin:bool,allowsNull:bool,isOptional:bool,default:mixed,mapKey:?string,attributes:array,reflectionParam:?ReflectionParameter}>
     */
    /**
     * @return array<int,array{name:string,typeName:?string,isBuiltin:bool,allowsNull:bool,isOptional:bool,default:mixed,mapKey:?string,attributes:array<int,array{class:string,arguments:array<int|string,mixed>}>,reflectionParam:?ReflectionParameter}>
     */
    public static function getConstructorParams(string $class): array
    {
        if (isset(self::$ctorParams[$class])) {
            return self::$ctorParams[$class];
        }

        if (! class_exists($class)) {
            self::$ctorParams[$class] = [];

            return [];
        }

        $persistentKey = self::persistentKeyForClass($class);
        $persisted = self::persistentFetch($persistentKey);

        // If persisted is present, verify it is not an incorrect empty entry for a class that actually has a constructor.
        if ($persisted !== null && class_exists($class)) {
            $refCheck = new ReflectionClass($class);
            $ctorCheck = $refCheck->getConstructor();
            if ($ctorCheck !== null && count($persisted) === 0) {
                // treat persisted empty as invalid for this class -> fallthrough to fresh reflection
                $persisted = null;
            }
        }

        if ($persisted !== null) {
            $result = [];
            foreach ($persisted as $entry) {
                $entry['reflectionParam'] = null;
                $result[] = $entry;
            }
            self::$ctorParams[$class] = $result;

            return $result;
        }

        $result = [];
        $ref = new ReflectionClass($class);

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            self::$ctorParams[$class] = $result;
            // persist empty result (valid: no constructor)
            self::persistentStore($persistentKey, []);

            return $result;
        }

        foreach ($ctor->getParameters() as $reflectionParameter) {
            $type = $reflectionParameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $isBuiltin = $type instanceof \ReflectionNamedType ? $type->isBuiltin() : false;

            $mapKey = null;

            $attributesMeta = [];
            foreach ($reflectionParameter->getAttributes() as $a) {
                $attributesMeta[] = [
                    'class' => $a->getName(),
                    'arguments' => $a->getArguments(),
                ];
            }

            if ($ref->hasProperty($reflectionParameter->getName())) {
                $property = $ref->getProperty($reflectionParameter->getName());
                foreach ($property->getAttributes() as $propAttr) {
                    $entry = [
                        'class' => $propAttr->getName(),
                        'arguments' => $propAttr->getArguments(),
                    ];

                    $alreadyPresent = false;
                    foreach ($attributesMeta as $attributeMetum) {
                        if ($attributeMetum['class'] === $entry['class'] && $attributeMetum['arguments'] === $entry['arguments']) {
                            $alreadyPresent = true;
                            break;
                        }
                    }

                    if (! $alreadyPresent) {
                        $attributesMeta[] = $entry;
                    }
                }
            }

            $result[] = [
                'name' => $reflectionParameter->getName(),
                'typeName' => $typeName,
                'isBuiltin' => $isBuiltin,
                'allowsNull' => $reflectionParameter->allowsNull(),
                'isOptional' => $reflectionParameter->isDefaultValueAvailable(),
                'default' => $reflectionParameter->isDefaultValueAvailable() ? $reflectionParameter->getDefaultValue() : null,
                'mapKey' => $mapKey,
                'attributes' => $attributesMeta,
                'reflectionParam' => $reflectionParameter,
            ];
        }

        $persistable = array_map(static function (array $entry): array {
            $e = $entry;
            unset($e['reflectionParam']);

            return $e;
        }, $result);

        self::persistentStore($persistentKey, $persistable);

        self::$ctorParams[$class] = $result;

        return $result;
    }

    /**
     * Return class-level attributes as serializable meta.
     *
     * @param  class-string  $class
     * @return array<int,array{class:string,arguments:array}>
     */
    /**
     * @return array<int,array{class:string,arguments:array<int|string,mixed>}>
     */
    public static function getClassAttributes(string $class): array
    {
        if (isset(self::$classAttributes[$class])) {
            return self::$classAttributes[$class];
        }

        if (! class_exists($class)) {
            self::$classAttributes[$class] = [];

            return [];
        }

        $persistentKey = self::persistentKeyForClassAttributes($class);
        $persisted = self::persistentFetch($persistentKey);
        if ($persisted !== null) {
            self::$classAttributes[$class] = $persisted;

            return $persisted;
        }

        $result = [];
        $reflectionClass = new ReflectionClass($class);

        foreach ($reflectionClass->getAttributes() as $attribute) {
            $result[] = [
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        self::persistentStore($persistentKey, $result);
        self::$classAttributes[$class] = $result;

        return $result;
    }

    /**
     * Clears runtime cache.
     */
    public static function clearRuntimeCache(): void
    {
        self::$ctorParams = [];
        self::$classAttributes = [];
    }

    /**
     * Clears persistent cache for a given class (if supported).
     *
     * @param  class-string  $class
     */
    public static function clearPersistent(string $class): void
    {
        if (! self::persistentAvailable()) {
            return;
        }

        $k1 = self::persistentKeyForClass($class);
        $k2 = self::persistentKeyForClassAttributes($class);

        if (class_exists(Cache::class)) {
            try {
                Cache::forget($k1);
                Cache::forget($k2);
                foreach (self::methodKeysForClass($class) as $methodKey) {
                    Cache::forget($methodKey);
                }
            } catch (\Throwable) {
            }

            return;
        }

        if (function_exists('apcu_delete')) {
            @apcu_delete($k1);
            @apcu_delete($k2);
            foreach (self::methodKeysForClass($class) as $methodKey) {
                @apcu_delete($methodKey);
            }
        }
    }

    /**
     * Clears both runtime and persistent cache for the given class.
     *
     * @param  class-string  $class
     */
    public static function clear(string $class): void
    {
        // Remove from runtime arrays
        unset(self::$ctorParams[$class], self::$classAttributes[$class]);
        self::clearMethodRuntimeCache($class);

        // Remove from persistent storage
        self::clearPersistent($class);
    }

    private static function clearMethodRuntimeCache(string $class): void
    {
        foreach (array_keys(self::$methodAttributes) as $key) {
            if (str_starts_with($key, $class.'::')) {
                unset(self::$methodAttributes[$key]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function methodKeysForClass(string $class): array
    {
        $keys = [];

        try {
            $reflectionClass = new ReflectionClass($class);
        } catch (\Throwable) {
            return $keys;
        }

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $keys[] = self::persistentKeyForMethodAttributes($class, $reflectionMethod->getName());
        }

        return $keys;
    }

    /**
     * Warm up both constructor params and class-level attributes
     * for a given ReflectionClass instance.
     */
    /**
     * @template T of object
     *
     * @param  ReflectionClass<T>  $reflectionClass
     */
    public static function warm(ReflectionClass $reflectionClass): void
    {
        $class = $reflectionClass->getName();

        // Preload constructor metadata
        self::getConstructorParams($class);

        // Preload class-level attributes
        self::getClassAttributes($class);
    }
}
