<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Request
{
    public function __construct(
        public string $formRequest,
        public ?string $inputDto = null
    ) {}
}
