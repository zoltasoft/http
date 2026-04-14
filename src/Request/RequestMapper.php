<?php

declare(strict_types=1);

namespace Zolta\Http\Request;

use ReflectionClass;
use ReflectionParameter;
use Zolta\Exceptions\ValidationException;
use Zolta\Support\Application\Attributes\FromRequest;
use Zolta\Support\Application\DTO\Interfaces\InputDTO;

final class RequestMapper
{
    /**
     * @template T of InputDTO
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<T>|null  $dtoClass
     * @return T|array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function map(array $data, ?string $dtoClass = null, ?callable $callback = null): InputDTO|array
    {
        if ($callback) {
            $data = $callback($data);
        }

        // Optional DTO: return array if no DTO class provided
        if ($dtoClass === null) {
            return $data;
        }

        $reflectionClass = new ReflectionClass($dtoClass);
        $constructor = $reflectionClass->getConstructor();
        $args = [];
        $errors = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $reflectionParameter) {
                $args[$reflectionParameter->getName()] = self::resolveParam($reflectionParameter, $data, $errors);
            }
        }

        /** @var InputDTO $inputDTO */
        $inputDTO = $reflectionClass->newInstanceArgs($args);

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isPromoted()) {
                continue;
            }
            if (! $reflectionProperty->isPublic()) {
            }

            $value = $reflectionProperty->getValue($inputDTO);
            $fieldName = $reflectionProperty->getName();

            foreach ($reflectionProperty->getAttributes() as $attr) {
                $instance = $attr->newInstance();
                if (method_exists($instance, 'validate')) {
                    $error = $instance->validate($value);
                    if ($error) {
                        $errors[$fieldName] = $error;
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $inputDTO;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private static function resolveParam(ReflectionParameter $reflectionParameter, array $data, array &$errors): mixed
    {
        $paramName = $reflectionParameter->getName();
        $fieldName = $paramName;

        $fromAttrs = $reflectionParameter->getAttributes(FromRequest::class, \ReflectionAttribute::IS_INSTANCEOF);
        if ($fromAttrs !== []) {
            $from = $fromAttrs[0]->newInstance();
            if (! empty($from->field)) {
                $fieldName = $from->field;
            }
        }

        if (! array_key_exists($fieldName, $data)) {
            if ($reflectionParameter->isOptional()) {
                return $reflectionParameter->getDefaultValue();
            }
            throw new ValidationException([$fieldName => "The field {$fieldName} is required."]);
        }

        $value = self::arrayGet($data, $fieldName);
        $typeRef = $reflectionParameter->getType();
        $type = $typeRef instanceof \ReflectionNamedType ? $typeRef->getName() : null;

        if ($type === 'bool') {
            return self::parseBoolean($value);
        }

        if (in_array($type, ['int', 'float', 'string'], true)) {
            settype($value, $type);

            return $value;
        }

        if (
            class_exists($type)
            && is_subclass_of($type, InputDTO::class)
            && is_array($value)
        ) {
            return self::mapNestedDto($type, $value, $errors);
        }

        return $value;
    }

    /**
     * @param  class-string<InputDTO>  $dtoClass
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private static function mapNestedDto(string $dtoClass, array $data, array &$errors): InputDTO
    {
        try {
            return self::map($data, $dtoClass);
        } catch (ValidationException $ex) {
            $errorsKey = (new ReflectionClass($dtoClass))->getShortName();
            $errors[$errorsKey] = $ex->getErrors();

            return (new ReflectionClass($dtoClass))->newInstanceWithoutConstructor();
        }
    }

    private static function parseBoolean(mixed $value): bool
    {
        $truthy = ['1', 'true', 'on', 'yes', 1, true];

        return in_array($value, $truthy, true);
    }

    /**
     * Lightweight replacement for Laravel data_get().
     *
     * @param  array<string, mixed>  $array
     */
    private static function arrayGet(array $array, string $key): mixed
    {
        if (str_contains($key, '.')) {
            foreach (explode('.', $key) as $segment) {
                if (! is_array($array) || ! array_key_exists($segment, $array)) {
                    return null;
                }
                $array = $array[$segment];
            }

            return $array;
        }

        return $array[$key] ?? null;
    }
}
