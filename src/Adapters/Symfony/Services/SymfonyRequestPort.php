<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Services;

use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Zolta\Http\Request\Contracts\RequestPort;

/**
 * Symfony-backed RequestPort adapter (scaffold).
 */
final readonly class SymfonyRequestPort implements RequestPort
{
    public function __construct(private mixed $requestStack) {}

    public function all(): array
    {
        $request = $this->currentRequest();
        if ($request === null) {
            return [];
        }

        $body = (array) ($request->request->all() ?? []);
        if ($body === [] && method_exists($request, 'toArray')) {
            try {
                $body = (array) $request->toArray();
            } catch (\Throwable) {
                // ignore JSON parse errors and fall back to empty body
            }
        }

        // Merge query + request + attributes for parity with Laravel adapter
        return array_replace(
            (array) ($request->query->all() ?? []),
            $body,
            (array) ($request->attributes->all() ?? [])
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $request = $this->currentRequest();
        if ($request === null) {
            return $default;
        }

        $all = $this->all();

        return $this->dataGet($all, $key, $default);
    }

    public function merge(array $data): void
    {
        $request = $this->currentRequest();
        if ($request === null) {
            return;
        }

        $request->request->add($data);
    }

    public function user(): mixed
    {
        $request = $this->currentRequest();

        return $request?->getUser() ?? ($request?->attributes->get('user') ?? null);
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $request = $this->currentRequest();
        if ($request === null) {
            return $default;
        }

        return $request->headers->get($key, $default);
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        $request = $this->currentRequest();

        return $request?->attributes->get($key, $default);
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        $request = $this->currentRequest();

        return $request?->query->get($key, $default);
    }

    private function currentRequest(): mixed
    {
        if (! self::isAvailable()) {
            throw new LogicException('Symfony HttpFoundation is required for SymfonyRequestPort');
        }

        if (is_object($this->requestStack) && method_exists($this->requestStack, 'getCurrentRequest')) {
            return $this->requestStack->getCurrentRequest();
        }

        return null;
    }

    public static function isAvailable(): bool
    {
        return class_exists(RequestStack::class);
    }

    /**
     * @param  array<string,mixed>  $array
     */
    private function dataGet(array $array, string $key, mixed $default = null): mixed
    {
        if (function_exists('data_get')) {
            return data_get($array, $key, $default);
        }

        // Simple dot-notation resolver
        $segments = explode('.', $key);
        $value = $array;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }
}
