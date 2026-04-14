<?php

declare(strict_types=1);

namespace Zolta\Http\Request\Laravel\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\MessageBag;
use Psr\Log\LoggerInterface;
use Zolta\Exceptions\ValidationException;

trait HandlesValidationFailure
{
    /**
     * Convert Laravel validator errors into a domain ValidationException.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $formattedErrors = $this->formatErrors($errors);

        // Log validation failure at info level via PSR logger from container (infra responsibility).
        // It's acceptable to use app() here because this trait runs in the infra (FormRequest).
        try {
            app(LoggerInterface::class)->info('validation.failed', ['errors' => $formattedErrors]);
        } catch (\Throwable) {
            // If logger unavailable, silently continue — do not break validation flow.
        }

        throw new ValidationException($formattedErrors);
    }

    /**
     * Format MessageBag into the minimal structured array expected by ValidationException.
     *
     * @return array<int, array<string, string>>
     */
    protected function formatErrors(MessageBag $messageBag): array
    {
        $formatted = [];

        foreach ($messageBag->getMessages() as $field => $messages) {
            foreach ($messages as $message) {
                $formatted[] = [
                    'type' => $field,
                    'message' => (string) $message,
                ];
            }
        }

        return $formatted;
    }
}
