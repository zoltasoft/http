<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Metadata;

use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Response\Attributes\Views\View;
use Zolta\Http\Service\Attributes\Service;

final readonly class AttributeMetadata
{
    public function __construct(
        public ?array $requestAttr,
        public ?array $serviceAttr,
        public ?array $responseAttr,
        public ?array $viewAttr
    ) {}

    public static function from(array $attrs): self
    {
        return new self(
            self::find($attrs, Request::class),
            self::find($attrs, Service::class),
            self::find($attrs, Response::class),
            self::find($attrs, View::class),
        );
    }

    public function isDirectController(): bool
    {
        return ! $this->requestAttr && ! $this->serviceAttr;
    }

    private static function find(array $attrs, string $class): ?array
    {
        foreach ($attrs as $attr) {
            if ($attr['class'] === $class) {
                return $attr;
            }
        }

        return null;
    }
}
