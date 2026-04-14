<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Contracts;

interface CoreRequestContract
{
    public function toInputDto(?string $dtoClass = null): mixed;

    /**
     * @param  string|array<int|string, mixed>  $action
     */
    public function authorizeAction(string|array $action, mixed $subject = null): void;

    /** @return array<string, mixed>|null */
    public function optionsPayload(): ?array;
}
