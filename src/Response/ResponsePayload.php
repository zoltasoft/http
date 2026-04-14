<?php

declare(strict_types=1);

namespace Zolta\Http\Response;

final readonly class ResponsePayload
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public array $errors = [],
        public array $debug = [],
    ) {}
}
