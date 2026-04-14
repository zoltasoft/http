<?php

// Laravel app stubs for PHPStan
if (! function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        return null;
    }
}
if (! function_exists('config')) {
    function config($key = null, $default = null): mixed
    {
        return $default;
    }
}
if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return $path;
    }
}
if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return $path;
    }
}
if (! function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return $path;
    }
}
if (! function_exists('request')) {
    function request(?string $key = null, $default = null): mixed
    {
        return null;
    }
}
if (! function_exists('response')) {
    function response($content = '', $status = 200, array $headers = []): mixed
    {
        return null;
    }
}
if (! function_exists('view')) {
    function view(?string $view = null, array $data = [], array $mergeData = []): mixed
    {
        return null;
    }
}
