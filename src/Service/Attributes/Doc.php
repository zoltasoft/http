<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Doc
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public array $tags = []
    ) {}
}
