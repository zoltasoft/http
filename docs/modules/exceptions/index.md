---
title: Exceptions
description: Exception handling, normalization, and API error responses.
navigation:
  title: Exceptions
  order: 6
---

# Exceptions

The Exceptions module provides exception handling and normalization for API responses. It ensures that all exceptions — including unexpected ones — are converted into consistent `ResponsePayload` error envelopes.

## HandlesApiExceptions trait

```php
use Zolta\Http\Exceptions\Traits\HandlesApiExceptions;
```

This trait provides a `handleExceptions()` method that wraps service execution in a try-catch with intelligent exception routing:

```php
public function handleExceptions(callable $callback): mixed;
```

### Behavior

| Exception Type | Action |
|----------------|--------|
| `ValidationException` | Rethrown as-is (framework handles validation errors) |
| `BaseException` | Rethrown as-is (domain/application exceptions) |
| Any other `Throwable` | Wrapped in `InternalServerErrorException` |

### Usage in services

```php
use Zolta\Http\Exceptions\Traits\HandlesApiExceptions;

class OrderService
{
    use HandlesApiExceptions;

    public function __invoke(CreateOrderDTO $input)
    {
        return $this->handleExceptions(function () use ($input) {
            // Business logic that might throw
            $order = Order::create($input->toArray());
            return new OrderResponseDTO(order: $order->toArray());
        });
    }
}
```

If the callback throws an unexpected exception (database error, third-party API failure, etc.), it's caught and converted to an `InternalServerErrorException` with a safe error message.

## Built-in exceptions

### ActionNotAllowedException

Thrown when a user lacks the required permissions for an action:

```php
use Zolta\Http\Exceptions\ActionNotAllowedException;

throw new ActionNotAllowedException('You do not have permission to delete users.');
```

**HTTP status:** 403 Forbidden

This exception is thrown automatically by the authorization system when `authorized` abilities in `#[Route]` are not satisfied.

### ControllerConfigurationException

Thrown when a controller is misconfigured (missing required attributes, invalid attribute combinations):

```php
use Zolta\Http\Exceptions\ControllerConfigurationException;

throw new ControllerConfigurationException('Service attribute is required.');
```

**HTTP status:** 500 Internal Server Error

This is a developer-facing exception that indicates a wiring problem, not a user error.

### InternalServerErrorException

Generic wrapper for unexpected exceptions:

```php
use Zolta\Http\Exceptions\InternalServerErrorException;

throw new InternalServerErrorException('An unexpected error occurred.');
```

**HTTP status:** 500 Internal Server Error

## Error response format

Exception responses follow the same envelope as success responses:

```json
{
    "success": false,
    "message": "You do not have permission to delete users.",
    "data": [],
    "errors": [
        {
            "code": "FORBIDDEN",
            "detail": "Missing required permissions: users.delete"
        }
    ],
    "debug": []
}
```

### Validation errors

Validation exceptions produce structured error arrays:

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "data": [],
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 6 characters."]
    },
    "debug": []
}
```

## Exception hierarchy

```
Throwable
├── ValidationException (framework-native, rethrown)
├── BaseException (domain exceptions, rethrown)
│   ├── ActionNotAllowedException (403)
│   └── ControllerConfigurationException (500)
└── Any other Throwable → InternalServerErrorException (500)
```

## Laravel adapter

The `Exceptions/Adapters/Laravel/` directory contains Laravel-specific exception rendering, integrating with Laravel's exception handler to produce `ResponsePayload`-formatted responses.

## Best practices

1. **Throw domain exceptions** in your service layer (extend `BaseException`)
2. **Use `HandlesApiExceptions`** to catch unexpected failures
3. **Never catch `ValidationException`** in services — let the framework handle it
4. **Keep error messages user-safe** — avoid leaking internal details in production
