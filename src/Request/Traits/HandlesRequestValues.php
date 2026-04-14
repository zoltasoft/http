<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Traits;

/**
 * @method mixed transformValue(mixed $value, string $type)
 * @method void setNestedValue(array<string, mixed> &$arr, string $key, mixed $value)
 */
trait HandlesRequestValues
{
    protected function transformValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'array' => is_array($value)
                ? $value
                : ((string) $value === ''
                    ? []
                    : array_map(trim(...), explode(',', (string) $value))
                ),
            'string' => (string) $value,
            default => $value
        };
    }

    /**
     * Set nested value in array using dot notation.
     *
     * @param  array<string, mixed>  $array
     */
    protected function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = &$array;

            while (count($keys) > 1) {
                $segment = array_shift($keys);
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }

            $current[array_shift($keys)] = $value;
        } else {
            $array[$key] = $value;
        }
    }
}
