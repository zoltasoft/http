---
title: Zolta HTTP
description: Domain-driven HTTP framework for PHP 8.2+ with attribute-based routing, request validation, DTO mapping, and standardized responses.
navigation:
  title: Introduction
  order: 0
---

# Zolta HTTP

Zolta HTTP is a domain-driven HTTP framework for PHP 8.2+. It provides a declarative, attribute-based approach to building APIs where controllers contain zero logic — all behavior is expressed through PHP attributes that bind routing, validation, service execution, and response shaping.

## Key features

- **Attribute-based routing** — Define routes, middleware, authentication, and authorization directly on controller methods using `#[Route]`.
- **Automatic request validation** — Bind form requests with `#[Request]` for rule-based validation and automatic input DTO mapping.
- **Service layer binding** — Connect controller actions to service classes via `#[Service]` with configurable success messages and HTTP status codes.
- **Response shaping** — Transform service output through resource classes using `#[Response]` for consistent API envelopes.
- **Authorization matrix** — Configure ability-to-permission mappings with wildcard support and automatic user permission resolution.
- **Dual framework support** — First-class adapters for both Laravel and Symfony, with a framework-agnostic core.
- **OpenAPI metadata** — Attach documentation metadata to endpoints with `#[Doc]`.

## How it works

A typical Zolta HTTP controller looks like this:

```php
final class UserController extends Controller
{
    #[Route(path: 'users/{id}', methods: ['GET'], auth: 'sanctum')]
    #[Request(GetUserRequest::class, GetUserDTO::class)]
    #[Service(GetUserService::class, 'User found.', 200)]
    #[Response(UserResource::class)]
    public function show() {}
}
```

The method body is empty. The framework reads the attributes at boot time and wires the full request lifecycle:

1. **Route** registers the endpoint with the framework router
2. **Request** validates incoming data and maps it to a typed DTO
3. **Service** receives the DTO, executes business logic, returns a response DTO
4. **Response** transforms the result through a resource class into a standardized JSON envelope

## Package modules

| Module | Namespace | Purpose |
|--------|-----------|---------|
| [Router](/modules/router) | `Zolta\Http\Router` | Route registration, middleware, auth guards |
| [Request](/modules/request) | `Zolta\Http\Request` | Validation, DTO mapping, form requests |
| [Response](/modules/response) | `Zolta\Http\Response` | Response payload, resources, HTTP responses |
| [Controller](/modules/controller) | `Zolta\Http\Controller` | Framework-agnostic base controller |
| [Service](/modules/service) | `Zolta\Http\Service` | Service binding, documentation, file uploads |
| [Exceptions](/modules/exceptions) | `Zolta\Http\Exceptions` | Exception handling and normalization |
| [Authorization](/modules/authorization) | `Zolta\Http\Authorization` | Abilities, permissions, user resolution |
| [Identity](/modules/identity) | `Zolta\Http\Identity\Laravel` | Remote Identity token introspection for Laravel |

## Requirements

- PHP 8.2+
- Laravel 10+ or Symfony 6+
- Composer
