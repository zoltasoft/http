<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Service
{
    public function __construct(
        public string $class,
        public string $successMessage = 'Request completed successfully.',
        public int $status = 200
    ) {}
}
