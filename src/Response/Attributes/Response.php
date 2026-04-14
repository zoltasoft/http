<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Response
{
    public function __construct(
        public string $resource,
        public string $method = 'toArray'
    ) {}
}
