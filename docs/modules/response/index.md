---
title: Response
description: Response payloads, resource transformation, and standardized HTTP output.
navigation:
  title: Response
  order: 3
---

# Response

The Response module standardizes API output through a consistent envelope format, resource-based transformation, and framework-agnostic response building.

## The Response attribute

```php
use Zolta\Http\Response\Attributes\Response;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Response
{
    public function __construct(
        public string $resource,
        public string $method = 'toArray',
    ) {}
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `resource` | `string` | — | Resource class (extends `Resource`) for response transformation |
| `method` | `string` | `'toArray'` | Method to call on the resource |

## ResponsePayload

The `ResponsePayload` is the standard envelope for all API responses:

```php
use Zolta\Http\Response\ResponsePayload;
```

### Structure

```php
readonly class ResponsePayload
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public array $errors = [],
        public array $debug = [],
    ) {}
}
```

### JSON output

Every API response follows this envelope:

```json
{
    "success": true,
    "message": "User found.",
    "data": {
        "user": {
            "id": "123",
            "name": "John Doe",
            "email": "john@example.com"
        }
    },
    "errors": [],
    "debug": []
}
```

**Success response:**
- `success`: `true`
- `message`: The success message from the `#[Service]` attribute
- `data`: Transformed output from the resource class
- `errors`: Empty array
- `debug`: Empty array (populated only in debug mode)

**Error response:**
- `success`: `false`
- `message`: Error description
- `data`: Empty array
- `errors`: Array of error details (validation errors, etc.)

## Resource

Resources transform raw service output (response DTOs) into the `data` portion of the response envelope.

```php
use Zolta\Http\Response\Resources\Resource;
```

### Base class

```php
abstract class Resource implements ApiResponseData
{
    public function __construct(
        ResponseDTOInterface|array $response,
        ?RouterService $routerService = null,
    ) {}

    abstract public function toArray(): array;

    protected function get(string $key): mixed;
    protected function set(string $key, mixed $value): void;
    protected function has(string $key): bool;
    protected function all(): array;
    protected function router(): mixed;
}
```

### Helper methods

| Method | Description |
|--------|-------------|
| `get($key)` | Get a property from the response DTO |
| `set($key, $value)` | Set a property on the response DTO |
| `has($key)` | Check if a property exists |
| `all()` | Get the entire DTO as an array |
| `router()` | Access the router service (for URL generation) |

### Creating a resource

```php
<?php

namespace App\Services\UserService\API\Resources;

use Zolta\Http\Response\Resources\Resource;

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'user' => [
                'id' => $this->get('user')['id'],
                'name' => $this->get('user')['name'],
                'email' => $this->get('user')['email'],
            ],
        ];
    }
}
```

### Accessing nested data

```php
final class UserDetailResource extends Resource
{
    public function toArray(): array
    {
        $user = $this->get('user');
        $role = $this->get('role');

        return [
            'user' => $user,
            'role' => $role,
            'permissions' => $this->all()['permissions'] ?? [],
        ];
    }
}
```

### List resources

For collection endpoints, return the array directly:

```php
final class RoleListResource extends Resource
{
    public function toArray(): array
    {
        return [
            'roles' => $this->all()['roles'],
        ];
    }
}
```

## HttpResponse

The `HttpResponse` helper converts payloads to framework-specific HTTP responses:

```php
use Zolta\Http\Response\HttpResponse;

// From a ResponsePayload
$response = HttpResponse::fromPayload($payload, status: 200);

// Inline construction
$response = HttpResponse::send(
    success: true,
    message: 'Created.',
    data: ['id' => 1],
    status: 201,
);
```

### Signature

```php
public static function fromPayload(
    ResponsePayload $responsePayload,
    int $status = 200,
): mixed;

public static function send(
    bool $success,
    string $message = '',
    array $data = [],
    array $errors = [],
    array $debug = [],
    int $status = 200,
): mixed;
```

The return type is framework-specific (`JsonResponse` in Laravel, `Response` in Symfony).

## View attribute

For server-rendered responses, use the `#[View]` attribute instead of `#[Response]`:

```php
use Zolta\Http\Response\Attributes\Views\View;

#[Route(path: 'dashboard', prefix: null)]
#[View(view: 'dashboard.index', engine: 'blade')]
public function dashboard() {}
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class View
{
    public function __construct(
        public string $view,
        public ?string $engine = null,
    ) {}
}
```

## Response bridge

The Response module uses a bridge pattern to produce framework-specific responses. The `ResponseBridge` contract is resolved at runtime:

- **Laravel**: Returns `Illuminate\Http\JsonResponse`
- **Symfony**: Returns `Symfony\Component\HttpFoundation\JsonResponse`

Application code works exclusively with `ResponsePayload` and `HttpResponse`, never with framework-specific types.
