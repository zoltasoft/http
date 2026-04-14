<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Validation;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Request\Interfaces\ValidatorInterface;

final readonly class LaravelValidatorAdapter implements ValidatorInterface
{
    public function __construct(private ValidationFactory $validationFactory) {}

    public function validate(array $data, array $rules): void
    {
        $validator = $this->validationFactory->make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->toArray());
        }
    }
}
