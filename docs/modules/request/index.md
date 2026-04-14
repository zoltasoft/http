---
title: Request
description: Form requests, validation rules, route parameters, query options, and automatic DTO mapping.
navigation:
  title: Request
  order: 2
---

# Request

The Request module handles input validation, route parameter binding, query options, and automatic mapping of validated data to typed input DTOs.

## The Request attribute

```php
use Zolta\Http\Request\Attributes\Request;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Request
{
    public function __construct(
        public string $formRequest,
        public ?string $inputDto = null,
    ) {}
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `formRequest` | `string` | — | Form request class (extends `BaseRequest`) |
| `inputDto` | `?string` | `null` | Optional input DTO class for automatic mapping |

## BaseRequest

All form requests extend `BaseRequest`, which provides a framework-agnostic interface for validation:

```php
use Zolta\Http\Request\BaseRequest;
```

### Key methods

| Method | Return | Description |
|--------|--------|-------------|
| `rules()` | `array` | Validation rules (Laravel validation syntax) |
| `authorize()` | `bool` | Whether the request is authorized |
| `messages()` | `array` | Custom validation error messages |
| `routeParams()` | `array` | Route parameter definitions |
| `queryOptions()` | `array` | Query string options (includes, filters) |
| `authorizeAction()` | `void` | Authorize a specific action via the AuthorizationMatrix |
| `toInputDto()` | `mixed` | Convert validated data to an input DTO |

### Example form request

```php
<?php

namespace App\Services\UserService\API\Requests;

use Zolta\Http\Request\BaseRequest;

final class GetUserByIdRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|string|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'The user ID is required.',
            'id.string' => 'The user ID must be a string.',
        ];
    }

    public function routeParams(): array
    {
        return [
            'id' => ['type' => 'string', 'required' => true],
        ];
    }

    public function queryOptions(): array
    {
        return [
            'include' => ['role'],
            'strict' => false,
        ];
    }
}
```

### Route parameters

The `routeParams()` method declares which URL segments should be extracted and merged into validated data:

```php
public function routeParams(): array
{
    return [
        'id' => ['type' => 'string', 'required' => true],
        'slug' => ['type' => 'string', 'required' => false],
    ];
}
```

For a route `users/{id}`, the `id` value from the URL is merged with body/query data before validation.

### Query options

The `queryOptions()` method defines default query parameters for the request. These are passed to the service layer for features like eager loading or filtering:

```php
public function queryOptions(): array
{
    return [
        'include' => ['role', 'permissions'],
        'strict' => true,
    ];
}
```

## Input DTO mapping

When the `#[Request]` attribute specifies an `inputDto`, validated data is automatically mapped to the DTO using the `RequestMapper`.

### Defining an input DTO

```php
<?php

use Zolta\Support\Application\DTO\Input\InputDTO;
use Zolta\Support\Application\Attributes\FromRequest;

class CreateUserDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('name')]
        public readonly string $name,

        #[FromRequest('email')]
        public readonly string $email,

        #[FromRequest('role_id')]
        public readonly int $roleId,

        #[FromRequest('bio')]
        public readonly ?string $bio = null,

        public readonly array $options = [],
    ) {}
}
```

### The `#[FromRequest]` attribute

Maps a DTO property to a specific request field:

```php
#[FromRequest('field_name')]
public readonly string $property;
```

Supports dot-notation for nested data:

```php
#[FromRequest('address.city')]
public readonly string $city;
```

### Type coercion

The `RequestMapper` automatically coerces values to their declared types:

| Target Type | Input | Result |
|-------------|-------|--------|
| `int` | `"42"` | `42` |
| `float` | `"3.14"` | `3.14` |
| `bool` | `"true"`, `"1"`, `"yes"`, `"on"` | `true` |
| `bool` | `"false"`, `"0"`, `"no"`, `"off"` | `false` |
| `string` | `42` | `"42"` |

### Nested DTOs

If a DTO property is typed as another DTO class, the mapper recursively maps nested data:

```php
class OrderDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('address')]
        public readonly AddressDTO $address,
    ) {}
}

class AddressDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('street')]
        public readonly string $street,
        #[FromRequest('city')]
        public readonly string $city,
    ) {}
}
```

Given input `{ "address": { "street": "123 Main", "city": "NYC" } }`, the mapper creates both DTOs.

### DTO validation

Properties with validation attributes are validated during mapping:

```php
use App\Attributes\Validation\Required;
use App\Attributes\Validation\MaxLength;

class CreateUserDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('name')]
        #[Required]
        #[MaxLength(255)]
        public readonly string $name,
    ) {}
}
```

Validation failures throw a `ValidationException`.

## RequestMapper

The static `RequestMapper::map()` method handles the mapping logic:

```php
use Zolta\Http\Request\RequestMapper;

$dto = RequestMapper::map(
    data: $validatedData,
    dtoClass: CreateUserDTO::class,
);
```

### Signature

```php
public static function map(
    array $data,
    ?string $dtoClass = null,
    ?callable $callback = null,
): InputDTO|array
```

If `$dtoClass` is `null`, the raw data array is returned. An optional `$callback` can post-process the mapped DTO.

## Bridge architecture

The Request module uses a bridge pattern to adapt framework-specific request objects to the framework-agnostic `BaseRequest`:

```
BaseRequest (class_alias → LaravelBridgeRequest or SymfonyBridgeRequest)
    │
    ▼
BridgeRequest (delegates to BridgeRequestDelegate)
    │
    ▼
Framework-specific FormRequest (Laravel) or Symfony Request
```

This allows form requests to use framework-native validation while providing a unified interface.
