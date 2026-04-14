<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Psr\Container\ContainerInterface;

final readonly class RequestDtoFactory
{
    public function __construct(private ContainerInterface $container) {}

    public function create(?array $requestAttr): mixed
    {
        if (! $requestAttr) {
            return null;
        }

        $requestClass = $requestAttr['arguments'][0] ?? null;
        $dtoClass = $requestAttr['arguments']['inputDto'] ?? null;

        if (! $requestClass) {
            return null;
        }

        $request = $this->container->get($requestClass);

        return $request->validate($dtoClass);
    }
}
