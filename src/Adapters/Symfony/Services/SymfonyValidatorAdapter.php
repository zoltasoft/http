<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Services;

use LogicException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Validation;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Request\Interfaces\ValidatorInterface;

/**
 * Symfony-backed validator adapter (scaffold).
 */
final readonly class SymfonyValidatorAdapter implements ValidatorInterface
{
    public function __construct(private mixed $validator) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     */
    public function validate(array $payload, array $rules): void
    {
        if (! self::isAvailable()) {
            throw new LogicException('Symfony Validator is required for SymfonyValidatorAdapter');
        }

        $validator = $this->validator instanceof \Symfony\Component\Validator\Validator\ValidatorInterface
            ? $this->validator
            : Validation::createValidator();
        $errors = [];

        foreach ($rules as $field => $rawRules) {
            $value = $payload[$field] ?? null;
            $constraints = $this->buildConstraints($rawRules);
            $isRequired = $this->isRequired($rawRules);

            if ($value === null && ! $isRequired) {
                continue;
            }

            if ($value === null && $isRequired) {
                $errors[] = [
                    'field' => $field,
                    'type' => 'required',
                    'message' => 'The field is required.',
                ];

                continue;
            }

            // If the rule expects an array, coerce simple comma-separated strings.
            if ($this->expectsArray($rawRules) && is_string($value)) {
                $value = $this->coerceArray($value);
            }

            if (! $constraints instanceof Sequentially) {
                continue;
            }

            $violations = $validator->validate($value, $constraints);
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $field,
                    'type' => $violation->getCode() ?? 'validation',
                    'message' => $violation->getMessage(),
                ];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    public static function isAvailable(): bool
    {
        return interface_exists('Symfony\\Component\\Validator\\ValidatorInterface')
            || interface_exists('Symfony\\Component\\Validator\\Validator\\ValidatorInterface');
    }

    /**
     * @param  string|array<int,string>  $rawRules
     */
    private function buildConstraints(string|array $rawRules): ?Sequentially
    {
        $rules = is_array($rawRules) ? $rawRules : explode('|', (string) $rawRules);
        $constraints = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $name = trim((string) $name);
                $param = $param !== null ? trim($param) : null;
            } else {
                $name = '';
                $param = null;
            }

            switch ($name) {
                case 'required':
                    // handled separately
                    break;
                case 'sometimes':
                    // ignore; handled by required check
                    break;
                case 'email':
                    $constraints[] = new Assert\Email;
                    break;
                case 'string':
                    $constraints[] = new Assert\Type('string');
                    break;
                case 'integer':
                case 'int':
                    $constraints[] = new Assert\Type('integer');
                    break;
                case 'array':
                    $constraints[] = new Assert\Type('array');
                    break;
                case 'numeric':
                    $constraints[] = new Assert\Type('numeric');
                    break;
                case 'min':
                    $constraints[] = new Assert\Length(min: (int) $param);
                    break;
                case 'max':
                    $constraints[] = new Assert\Length(max: (int) $param);
                    break;
                default:
                    // unsupported rules ignored in this scaffold
                    break;
            }
        }

        if ($constraints === []) {
            return null;
        }

        return new Sequentially(constraints: $constraints);
    }

    /**
     * @param  string|array<int,string>  $rawRules
     */
    private function isRequired(string|array $rawRules): bool
    {
        $rules = is_array($rawRules) ? $rawRules : explode('|', (string) $rawRules);

        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'required')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string|array<int,string>  $rawRules
     */
    private function expectsArray(string|array $rawRules): bool
    {
        $rules = is_array($rawRules) ? $rawRules : explode('|', (string) $rawRules);

        foreach ($rules as $rule) {
            if (is_string($rule) && trim($rule) === 'array') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function coerceArray(string $value): array
    {
        $items = array_map(trim(...), explode(',', $value));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }
}
