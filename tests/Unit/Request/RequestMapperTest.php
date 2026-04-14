<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Zolta\Exceptions\ValidationException;
use Zolta\Http\Request\RequestMapper;
use Zolta\Support\Application\DTO\Interfaces\InputDTO;

// ── Test DTOs ────────────────────────────────────────────────────────────────

class SimpleUserDto implements InputDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email];
    }
}

class DtoWithOptionals implements InputDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $age = 25,
        public readonly ?string $bio = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'age' => $this->age, 'bio' => $this->bio];
    }
}

class DtoWithBool implements InputDTO
{
    public function __construct(
        public readonly bool $active,
        public readonly bool $verified = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['active' => $this->active, 'verified' => $this->verified];
    }
}

class DtoWithTypedScalars implements InputDTO
{
    public function __construct(
        public readonly int $count,
        public readonly float $price,
        public readonly string $label,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['count' => $this->count, 'price' => $this->price, 'label' => $this->label];
    }
}

class AddressDto implements InputDTO
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['street' => $this->street, 'city' => $this->city];
    }
}

class UserWithAddressDto implements InputDTO
{
    public function __construct(
        public readonly string $name,
        public readonly AddressDto $address,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['name' => $this->name, 'address' => $this->address->toArray()];
    }
}

// ── Tests ────────────────────────────────────────────────────────────────────

final class RequestMapperTest extends TestCase
{
    // ── Basic mapping ─────────────────────────────────────────────────────

    public function test_maps_array_to_simple_dto(): void
    {
        $data = ['name' => 'Alice', 'email' => 'alice@example.com'];

        $dto = RequestMapper::map($data, SimpleUserDto::class);

        $this->assertInstanceOf(SimpleUserDto::class, $dto);
        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
    }

    public function test_returns_raw_array_when_no_dto_class(): void
    {
        $data = ['foo' => 'bar', 'baz' => 42];

        $result = RequestMapper::map($data);

        $this->assertIsArray($result);
        $this->assertSame($data, $result);
    }

    // ── Callback / transform ──────────────────────────────────────────────

    public function test_callback_transforms_data_before_mapping(): void
    {
        $data = ['NAME' => 'BOB', 'EMAIL' => 'bob@test.com'];

        $dto = RequestMapper::map(
            $data,
            SimpleUserDto::class,
            fn (array $d) => array_change_key_case($d, CASE_LOWER),
        );

        $this->assertInstanceOf(SimpleUserDto::class, $dto);
        $this->assertSame('BOB', $dto->name);
        $this->assertSame('bob@test.com', $dto->email);
    }

    public function test_callback_applied_without_dto_class(): void
    {
        $data = ['x' => 1];

        $result = RequestMapper::map($data, null, fn (array $d) => ['x' => $d['x'] * 10]);

        $this->assertSame(['x' => 10], $result);
    }

    // ── Optional / default parameters ─────────────────────────────────────

    public function test_optional_params_use_defaults_when_absent(): void
    {
        $data = ['name' => 'Carol'];

        $dto = RequestMapper::map($data, DtoWithOptionals::class);

        $this->assertInstanceOf(DtoWithOptionals::class, $dto);
        $this->assertSame('Carol', $dto->name);
        $this->assertSame(25, $dto->age);
        $this->assertNull($dto->bio);
    }

    public function test_optional_params_overridden_when_provided(): void
    {
        $data = ['name' => 'Dave', 'age' => 30, 'bio' => 'Hello!'];

        $dto = RequestMapper::map($data, DtoWithOptionals::class);

        $this->assertSame(30, $dto->age);
        $this->assertSame('Hello!', $dto->bio);
    }

    // ── Boolean coercion ──────────────────────────────────────────────────

    /**
     * @dataProvider booleanTruthyProvider
     */
    public function test_boolean_truthy_values(mixed $input): void
    {
        $dto = RequestMapper::map(['active' => $input], DtoWithBool::class);

        $this->assertInstanceOf(DtoWithBool::class, $dto);
        $this->assertTrue($dto->active);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function booleanTruthyProvider(): array
    {
        return [
            'string 1' => ['1'],
            'string true' => ['true'],
            'string on' => ['on'],
            'string yes' => ['yes'],
            'int 1' => [1],
            'bool true' => [true],
        ];
    }

    /**
     * @dataProvider booleanFalsyProvider
     */
    public function test_boolean_falsy_values(mixed $input): void
    {
        $dto = RequestMapper::map(['active' => $input], DtoWithBool::class);

        $this->assertInstanceOf(DtoWithBool::class, $dto);
        $this->assertFalse($dto->active);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function booleanFalsyProvider(): array
    {
        return [
            'string 0' => ['0'],
            'string false' => ['false'],
            'string no' => ['no'],
            'int 0' => [0],
            'bool false' => [false],
            'empty string' => [''],
            'null' => [null],
        ];
    }

    // ── Scalar type coercion ──────────────────────────────────────────────

    public function test_string_values_coerced_to_typed_scalars(): void
    {
        $data = ['count' => '42', 'price' => '9.99', 'label' => 'item'];

        $dto = RequestMapper::map($data, DtoWithTypedScalars::class);

        $this->assertInstanceOf(DtoWithTypedScalars::class, $dto);
        $this->assertSame(42, $dto->count);
        $this->assertSame(9.99, $dto->price);
        $this->assertSame('item', $dto->label);
    }

    // ── Nested DTO mapping ────────────────────────────────────────────────

    public function test_nested_dto_mapped_recursively(): void
    {
        $data = [
            'name' => 'Eve',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Springfield',
            ],
        ];

        $dto = RequestMapper::map($data, UserWithAddressDto::class);

        $this->assertInstanceOf(UserWithAddressDto::class, $dto);
        $this->assertSame('Eve', $dto->name);
        $this->assertInstanceOf(AddressDto::class, $dto->address);
        $this->assertSame('123 Main St', $dto->address->street);
        $this->assertSame('Springfield', $dto->address->city);
    }

    // ── Validation: missing required field ─────────────────────────────────

    public function test_throws_validation_exception_on_missing_required_field(): void
    {
        $this->expectException(ValidationException::class);

        RequestMapper::map(['name' => 'Frank'], SimpleUserDto::class);
    }

    public function test_validation_exception_contains_field_name(): void
    {
        try {
            RequestMapper::map([], SimpleUserDto::class);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
        }
    }

    // ── Dot-notation / nested key access ──────────────────────────────────

    public function test_extra_keys_silently_ignored(): void
    {
        $data = ['name' => 'Grace', 'email' => 'g@test.com', 'extra' => 'ignored'];

        $dto = RequestMapper::map($data, SimpleUserDto::class);

        $this->assertSame('Grace', $dto->name);
    }
}
