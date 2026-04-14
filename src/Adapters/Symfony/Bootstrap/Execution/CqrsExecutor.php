<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Zolta\Http\Symfony\Services\CqrsLocator as ServicesCqrsLocator;

final readonly class CqrsExecutor
{
    public function __construct(
        private ServicesCqrsLocator $servicesCqrsLocator
    ) {}

    public function execute(array $serviceAttr, object $dto): mixed
    {
        $service = $serviceAttr['args']['class'] ?? null;

        if (! $service) {
            throw new \RuntimeException('Missing CQRS service.');
        }

        return $this->servicesCqrsLocator->get($service)->__invoke($dto);
    }
}
