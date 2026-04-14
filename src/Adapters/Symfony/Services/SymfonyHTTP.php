<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Services;

use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zolta\Http\Service\Contracts\HTTP;

/**
 * Symfony-backed HTTP adapter.
 */
final readonly class SymfonyHTTP implements HTTP
{
    public function __construct(private mixed $requestStack) {}

    public function request(): Request
    {
        if (! self::isAvailable()) {
            throw new LogicException('Symfony HttpFoundation is required for SymfonyHTTP');
        }

        $current = $this->requestStack?->getCurrentRequest();
        if ($current === null) {
            throw new LogicException('No current Symfony request available from RequestStack');
        }

        return $current;
    }

    public static function isAvailable(): bool
    {
        return class_exists(RequestStack::class);
    }
}
