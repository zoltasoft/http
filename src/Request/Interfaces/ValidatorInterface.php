<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Interfaces;

interface ValidatorInterface
{
    /**
     * Validate $data against $rules.
     * Must throw a domain ValidationException on failure.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    public function validate(array $data, array $rules): void;
}
