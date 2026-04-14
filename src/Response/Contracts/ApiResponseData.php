<?php

declare(strict_types=1);

namespace Zolta\Http\Response\Contracts;

interface ApiResponseData
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
