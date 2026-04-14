---
title: Router
description: Attribute-based route registration with middleware, authentication, and authorization support.
navigation:
  title: Router
  order: 1
---

# Router

The Router module provides attribute-based route registration. Routes are declared directly on controller classes and methods using the `#[Route]` attribute, eliminating the need for separate route files.

## The Route attribute

```php
use Zolta\Http\Router\Attributes\Route;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public ?string $prefix = 'api',
        public ?string $target = '__invoke',
        public array $middleware = [],
        public bool|string|null $auth = null,
        public array $authorized = [],
        public ?string $name = null,
    ) {}
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `path` | `string` | — | Route path (supports `{param}` placeholders) |
| `methods` | `array` | `['GET']` | HTTP methods (`GET`, `POST`, `PUT`, `DELETE`, `PATCH`) |
| `prefix` | `?string` | `'api'` | URL prefix. Set to `null` for no prefix |
| `target` | `?string` | `'__invoke'` | Target method name (for class-level routes) |
| `middleware` | `array` | `[]` | Middleware stack |
| `auth` | `bool\|string\|null` | `null` | Authentication guard (`'sanctum'`, `true`, or `null`) |
| `authorized` | `array` | `[]` | Required abilities from the AuthorizationMatrix |
| `name` | `?string` | `null` | Named route identifier |

## Usage examples

### Basic GET route

```php
#[Route(path: 'books', methods: ['GET'])]
public function list() {}
```

Registers: `GET /api/books`

### POST with middleware

```php
#[Route(path: 'books', methods: ['POST'], middleware: ['api', 'auth:sanctum'])]
public function create() {}
```

Registers: `POST /api/books` with `api` and `auth:sanctum` middleware.

### Route parameters

```php
#[Route(path: 'books/{id}', methods: ['GET'], middleware: ['api'])]
public function show() {}
```

Registers: `GET /api/books/{id}`

### Authentication

The `auth` parameter applies an authentication guard:

```php
#[Route(path: 'users/{id}', methods: ['GET'], auth: 'sanctum')]
public function show() {}
```

This is equivalent to applying `auth:sanctum` middleware, but with explicit semantic meaning.

### Authorization

The `authorized` parameter requires specific abilities from the `AuthorizationMatrix`:

```php
#[Route(path: 'users/{id}', methods: ['DELETE'], auth: 'sanctum', authorized: ['can_manage_users'])]
public function delete() {}
```

The user must have all permissions mapped to the `can_manage_users` ability. See [Authorization](/modules/authorization) for configuration.

### Named routes

```php
#[Route(path: 'auth/login', methods: ['POST'], name: 'auth.login')]
public function login() {}
```

### Custom prefix

```php
// No prefix — registers at /health
#[Route(path: 'health', prefix: null)]
public function health() {}

// Custom prefix — registers at /admin/dashboard
#[Route(path: 'dashboard', prefix: 'admin')]
public function dashboard() {}
```

### Multiple methods

```php
#[Route(path: 'books/{id}', methods: ['PUT', 'PATCH'])]
public function update() {}
```

## Route discovery

### Laravel

Routes are discovered automatically at boot time by scanning controller classes for `#[Route]` attributes. The `ZoltaHttpServiceProvider` handles registration.

Use the artisan command to cache discovered routes for production:

```bash
php artisan zolta:route:cache
```

### Symfony

The Symfony adapter discovers routes through the `RoutingBootstrap` compiler pass, which scans configured controller directories and registers routes with the Symfony router.

## Reflection cache

The router uses `ReflectionCache` to avoid repeated reflection calls on controller classes. Metadata (attributes, method signatures) is cached after first resolution and reused across requests.

```php
use Zolta\Http\Router\Cache\ReflectionCache;

// Cache is populated automatically during route resolution
$metadata = ReflectionCache::get(UserController::class);
```

## Complete example

```php
use Zolta\Http\Controller\Controller;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Service\Attributes\Service;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Service\Attributes\Doc;

final class RoleController extends Controller
{
    #[Route(path: 'roles', methods: ['GET'], middleware: ['api', 'auth:sanctum'], name: 'roles.list')]
    #[Service(ListRolesService::class, 'Request resolved successfully.', 200)]
    #[Response(RoleListResource::class)]
    #[Doc(summary: 'List all roles')]
    public function list() {}

    #[Route(path: 'roles', methods: ['POST'], middleware: ['api', 'auth:sanctum'], name: 'roles.create')]
    #[Request(CreateRoleRequest::class, CreateRoleInputDTO::class)]
    #[Service(CreateRoleService::class, 'Role created successfully.', 201)]
    #[Response(RoleResource::class)]
    #[Doc(summary: 'Create a new role')]
    public function create() {}

    #[Route(path: 'roles/{id}', methods: ['DELETE'], middleware: ['api', 'auth:sanctum'], name: 'roles.delete')]
    #[Request(DeleteRoleRequest::class, DeleteRoleInputDTO::class)]
    #[Service(DeleteRoleService::class, 'Role deleted successfully.', 204)]
    #[Doc(summary: 'Delete a role by ID')]
    public function delete() {}
}
```
