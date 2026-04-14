<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param  array<int, string>  $methods
     * @param  array<int, string>  $middleware
     */
    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public ?string $prefix = 'api',
        public ?string $target = '__invoke',
        public array $middleware = [],
        public bool|string|null $auth = null,
        /** @var array<int|string,string> */
        public array $authorized = [],
        public ?string $name = null,
    ) {}
}
