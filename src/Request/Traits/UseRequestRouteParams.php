<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Traits;

use Zolta\Http\Request\Laravel\Bridge\LaravelBridgeRequest;

/**
 * @method void mergeRouteParams()
 * @method mixed getRouteParamsValue(mixed $value, array<string, mixed> $config = [])
 */
trait UseRequestRouteParams
{
    use HandlesRequestValues;

    protected bool $__request_routeParams_merged = false;

    protected function mergeRouteParams(): void
    {
        if ($this->__request_routeParams_merged) {
            return;
        }

        $routeParamsConfig = $this->routeParams();

        if ($this instanceof LaravelBridgeRequest) {
            $route = $this->getRouteParameters();
        } else {
            $route = $this->route();
        }
        /** @var array<string,mixed> $routeParamsParameters */
        $routeParamsParameters = is_array($route) ? $route : [];

        $mapped = [];

        foreach ($routeParamsConfig as $key => $config) {
            if (is_numeric($key)) {
                $key = $config;
                $config = [];
            }

            if (! array_key_exists($key, $routeParamsParameters)) {
                continue;
            }

            $value = $this->getRouteParamsValue($routeParamsParameters[$key], $config);

            if ($value !== null) {
                $this->setNestedValue($mapped, $key, $value);
            }
        }

        if ($mapped !== []) {
            $this->merge($mapped);
        }

        $this->__request_routeParams_merged = true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function getRouteParamsValue(mixed $value, array $config = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (isset($config['type'])) {
            return $this->transformValue($value, $config['type']);
        }

        return $value;
    }
}
