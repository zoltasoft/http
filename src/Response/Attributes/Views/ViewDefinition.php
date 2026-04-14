<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Attributes\Views;

final readonly class ViewDefinition
{
    public function __construct(
        public string $view,
        public ?string $engine = null
    ) {}
}
