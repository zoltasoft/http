<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Traits;

/**
 * @method void mergeQueryParams()
 * @method mixed getQueryParamsValue(string $key, array<string, mixed> $config = [])
 */
trait UseRequestQueryParams
{
    use HandlesRequestValues;

    /**
     * Guard to prevent double merging
     */
    protected bool $__request_queryParams_merged = false;

    protected function mergeQueryParams(): void
    {
        if ($this->__request_queryParams_merged) {
            return;
        }

        if (! method_exists($this, 'queryParams')) {
            return;
        }

        $queryParamsConfig = $this->queryParams();
        if (! is_array($queryParamsConfig) || $queryParamsConfig === []) {
            $this->__request_queryParams_merged = true;

            return; // early exit
        }

        $mapped = [];

        foreach ($queryParamsConfig as $key => $config) {
            if (is_numeric($key)) {
                $key = $config;
                $config = [];
            }

            $value = $this->getQueryParamsValue($key, $config);

            if ($value !== null) {
                $this->setNestedValue($mapped, $key, $value);
            }
        }

        if ($mapped !== []) {
            $this->merge($mapped);
        }

        $this->__request_queryParams_merged = true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function getQueryParamsValue(string $key, array $config = []): mixed
    {
        $value = method_exists($this, 'getQueryParamValue') ? $this->getQueryParamValue($key) : $this->query($key);

        if ($value === null && array_key_exists('default', $config)) {
            // only apply default if value is strictly null (preserve falsy '0' etc.)
            $value = $config['default'];
        }

        if ($value === null) {
            return null;
        }

        if (isset($config['delimiter']) && $config['delimiter'] !== '') {
            $value = $this->explodeQueryParamValue($value, (string) $config['delimiter']);
        }

        if (isset($config['type'])) {
            $value = $this->transformValue($value, $config['type']);
        }

        return $value;
    }

    /**
     * @param  string|array<int|string, mixed>  $value
     * @return array<string>
     */
    private function explodeQueryParamValue(mixed $value, string $delimiter): array
    {
        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $item): string => trim((string) $item), $value));
        }

        $items = explode($delimiter, (string) $value);

        $filtered = array_filter(array_map(trim(...), $items), static fn (string $item): bool => $item !== '');

        return array_values($filtered);
    }
}
