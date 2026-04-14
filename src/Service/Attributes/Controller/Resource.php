<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Attributes\Controller;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Resource
{
    public function __construct(public string $class) {}
}
