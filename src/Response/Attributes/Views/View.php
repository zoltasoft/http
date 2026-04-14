<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Attributes\Views;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class View
{
    public function __construct(
        public string $view,
        public ?string $engine = null
    ) {}
}
