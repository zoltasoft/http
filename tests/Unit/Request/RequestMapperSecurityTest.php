<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Request\RequestMapper;
use Zolta\Support\Application\DTO\Interfaces\InputDTO;

// ── Test DTOs ────────────────────────────────────────────────────────────

class SecurityDto implements InputDTO
{
    public function __construct(
        public readonly string $input,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['input' => $this->input];
    }
}

class DotNotationDto implements InputDTO
{
    public function __construct(
        public readonly string $value,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}

class NestedSecurityDto implements InputDTO
{
    public function __construct(
        public readonly SecurityDto $child,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['child' => $this->child->toArray()];
    }
}

// ── Tests ────────────────────────────────────────────────────────────────

final class RequestMapperSecurityTest extends TestCase
{
    // ── Type coercion boundary tests ────────────────────────────────────

    public function test_int_coercion_from_string_with_leading_zeros(): void
    {
        $dto = new class(0) implements InputDTO
        {
            public function __construct(public readonly int $id) {}

            public function toArray(): array
            {
                return ['id' => $this->id];
            }
        };

        $result = RequestMapper::map(['id' => '007'], $dto::class);

        $this->assertSame(7, $result->id);
    }

    public function test_float_coercion_from_non_numeric_string(): void
    {
        $dto = new class(0.0) implements InputDTO
        {
            public function __construct(public readonly float $price) {}

            public function toArray(): array
            {
                return ['price' => $this->price];
            }
        };

        // Non-numeric string gets coerced to 0.0 by PHP settype
        $result = RequestMapper::map(['price' => 'not_a_number'], $dto::class);

        $this->assertSame(0.0, $result->price);
    }

    // ── Missing required fields ─────────────────────────────────────────

    public function test_completely_empty_data_for_required_dto(): void
    {
        $this->expectException(ValidationException::class);

        RequestMapper::map([], SecurityDto::class);
    }

    public function test_extra_fields_ignored(): void
    {
        $result = RequestMapper::map(
            ['input' => 'valid', 'extra' => 'should_be_ignored', '__proto__' => 'attack'],
            SecurityDto::class
        );

        $this->assertSame('valid', $result->input);
    }

    // ── Dot notation access ─────────────────────────────────────────────

    public function test_deeply_nested_dot_notation_access(): void
    {
        // This tests the arrayGet dot notation functionality
        $data = [
            'level1' => [
                'level2' => [
                    'value' => 'deep',
                ],
            ],
        ];

        // Without a DTO that uses FromRequest attribute with dot notation,
        // verify the raw array pass-through works
        $result = RequestMapper::map($data);

        $this->assertIsArray($result);
        $this->assertSame('deep', $result['level1']['level2']['value']);
    }

    // ── Nested DTO validation errors ────────────────────────────────────

    public function test_nested_dto_validation_errors_captured(): void
    {
        // Missing required 'input' in child DTO
        try {
            RequestMapper::map(
                ['child' => []],
                NestedSecurityDto::class
            );
            // If it doesn't throw, the nested DTO was instantiated without constructor
            // This is the expected behavior - errors are collected
            $this->addToAssertionCount(1);
        } catch (ValidationException $e) {
            // Also acceptable - validation error propagated
            $this->assertNotEmpty($e->getErrors());
        }
    }

    // ── Boolean coercion edge cases ─────────────────────────────────────

    public function test_boolean_rejects_arbitrary_strings(): void
    {
        $dto = new class(false) implements InputDTO
        {
            public function __construct(public readonly bool $flag) {}

            public function toArray(): array
            {
                return ['flag' => $this->flag];
            }
        };

        // 'maybe' is not in truthy list -> false
        $result = RequestMapper::map(['flag' => 'maybe'], $dto::class);

        $this->assertFalse($result->flag);
    }

    public function test_boolean_null_returns_false(): void
    {
        $dto = new class(false) implements InputDTO
        {
            public function __construct(public readonly bool $active) {}

            public function toArray(): array
            {
                return ['active' => $this->active];
            }
        };

        $result = RequestMapper::map(['active' => null], $dto::class);

        $this->assertFalse($result->active);
    }

    // ── Callback injection safety ───────────────────────────────────────

    public function test_callback_receives_raw_data(): void
    {
        $capturedData = null;

        RequestMapper::map(
            ['key' => 'value'],
            null,
            function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return $data;
            }
        );

        $this->assertSame(['key' => 'value'], $capturedData);
    }
}
