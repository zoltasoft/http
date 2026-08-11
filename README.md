# Zolta HTTP

**Declarative APIs. Zero boilerplate.**

Define your entire HTTP pipeline — routing, validation, service binding, authorization, and response shaping — with PHP 8 attributes. Your controller methods stay empty. The framework does the wiring.

```php
#[Route(path: 'users/{id}', methods: ['GET'], auth: 'sanctum', authorized: ['can_manage_users'])]
#[Request(GetUserByIdRequest::class, GetUserByIdDTO::class)]
#[Service(GetUserByIdService::class, 'User found.', 200)]
#[Response(UserResource::class)]
#[Doc(summary: 'Get a user by ID')]
public function show() {}  // That's it. The entire endpoint.
```

First-class Laravel (Symfony support coming soon) adapters. OpenAPI generation from the same attributes. Under 2ms pipeline overhead.

---

## Why Zolta HTTP?

### The problem

PHP frameworks give you routing and controllers, but the plumbing between "HTTP request" and "business logic" is still manual. Every endpoint repeats the same pattern: validate input → map to DTO → call service → transform result → shape response. This boilerplate multiplies across hundreds of endpoints, and every copy is a place for inconsistencies to hide.

### What Zolta HTTP does differently

| Approach | How it works | Trade-off |
|----------|-------------|-----------|
| Laravel Resource Controllers | Convention-based CRUD + manual service calls | Still wiring validation, DTOs, responses by hand |
| Symfony API Platform | Schema-driven REST/GraphQL generation | Heavy, opinionated, hard to customize beyond CRUD |
| Spatie Query Builder | Query parameter parsing for Eloquent | Read-only filtering, no full pipeline |
| **Zolta HTTP** | **Full pipeline declared in attributes — routing to response** | **True zero-boilerplate endpoints, dual framework support** |

The key insight: your controller method shouldn't *contain* the pipeline — it should *declare* it. Five attributes replace what typically takes 30-50 lines of wiring code per endpoint.

### Who is this for?

- Teams building **API-first applications** who are tired of repetitive controller boilerplate
- Projects targeting **both Laravel and Symfony** with shared HTTP logic
- Developers who want **convention with escape hatches** — use attributes for 90% of endpoints, drop to manual for the rest

## Install

```bash
composer require zolta/http
```

Laravel auto-discovers the service provider. For Symfony, register the bundle in `config/bundles.php`.

### Identity token introspection (Laravel)

`v2.1.0` adds optional remote Identity token validation. Publish the configuration, set `IDENTITY_API_URL`, `IDENTITY_CLIENT_ID`, and `IDENTITY_CLIENT_SECRET`, then protect a route with `identity.introspect`:

```bash
php artisan vendor:publish --tag=identity-consumer-config
```

```php
Route::get('/profile', ProfileController::class)->middleware('identity.introspect');
```

See the [Identity module documentation](docs/modules/identity/index.md) for sandbox connections, permission checks, caching, and webhook signature verification.

---

## The pipeline — from request to response

Every attribute-routed request flows through a deterministic pipeline:

```
HTTP Request
  ↓ RouteMetadataResolver   — Combine class + method attributes (cached)
  ↓ RouteConfigValidator     — Validate controller setup
  ↓ Authorization            — Check gates from #[Route] authorized
  ↓ FormRequest validation   — Apply rules from #[Request]
  ↓ DTO mapping              — Map validated data to typed object
  ↓ ServiceInvoker           — Call the bound service class
  ↓ ResourceTransformer      — Apply #[Response] resource transformation
  ↓ ResponseFactory          — Wrap in standardized envelope
HTTP Response
```

Each step is independently replaceable. The entire pipeline adds **< 2ms overhead** on warm requests.

---

## Quick start

### 1. Define a controller

Controllers are **declaration sites**, not logic containers. Each method declares *what* should happen via attributes — the framework handles *how*:

```php
use Zolta\Http\Controller\Controller;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Service\Attributes\Service;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Service\Attributes\Doc;

final class UserController extends Controller
{
    #[Route(path: 'users/{id}', methods: ['GET'], auth: 'sanctum', authorized: ['can_manage_users'])]
    #[Request(GetUserByIdRequest::class, GetUserByIdDTO::class)]
    #[Service(GetUserByIdService::class, 'User found.', 200)]
    #[Response(UserResource::class)]
    #[Doc(summary: 'Get a user by ID')]
    public function show() {}
}
```

### 2. Define a form request

```php
use Zolta\Http\Request\BaseRequest;

final class GetUserByIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => 'required|string|min:1'];
    }

    public function routeParams(): array
    {
        return ['id' => ['type' => 'string', 'required' => true]];
    }
}
```

Authenticated actors, tenant identifiers, and other server-owned values belong in
`trustedData()`. They are merged after validation and always replace client input with
the same key:

```php
public function trustedData(): array
{
    return ['user_id' => (string) $this->user()->getAuthIdentifier()];
}
```

Never accept an actor or tenant identifier from query parameters or the request body.
The former `withData()` hook remains available as a deprecated migration bridge and is
treated with the same trusted precedence.

### 3. Define an input DTO

```php
use Zolta\Support\Application\DTO\Input\InputDTO;
use Zolta\Support\Application\Attributes\FromRequest;

class GetUserByIdDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('id')]
        public readonly string $id,
        public readonly array $options = []
    ) {}
}
```

### 4. Define a resource

```php
use Zolta\Http\Response\Resources\Resource;

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'user' => $this->all()['user'],
        ];
    }
}
```

## Attribute reference

| Attribute | Target | Purpose |
|-----------|--------|---------|
| `#[Route]` | Class / Method | Defines path, HTTP methods, middleware, auth, authorization |
| `#[Request]` | Class / Method | Binds a form request class and optional input DTO |
| `#[Service]` | Class / Method | Binds a service class, success message, and HTTP status |
| `#[Response]` | Class / Method | Binds a resource class for response transformation |
| `#[Doc]` | Class / Method | OpenAPI documentation metadata (summary, description, tags) |
| `#[View]` | Class / Method | Server-side view rendering (view name, engine) |

## Authorization

Configure the `AuthorizationMatrix` to map abilities to permissions, with multi-path extraction from your user model:

```php
AuthorizationMatrix::configure([
    'abilities' => [
        'can_manage_users' => ['users.read', 'users.write'],
    ],
    'user' => [
        'class' => User::class,
        'attributes' => ['permissions', 'role.permissions', 'roles.*.permissions'],
    ],
]);
```

Then reference abilities in your route attribute — authorization is checked before the service is invoked:

```php
#[Route(path: '/users/{id}', auth: 'sanctum', authorized: ['can_manage_users'])]
```

## Response format

All responses follow a consistent envelope — success, error, and debug states share the same shape:

```json
{
    "success": true,
    "message": "User found.",
    "data": { "user": { "id": "123", "name": "John" } },
    "errors": [],
    "debug": []
}
```

Exceptions are automatically normalized: domain exceptions (404, 409, 422) pass through with context; unexpected errors become safe 500 responses with debug trace in development mode.

---

## What else is included

### OpenAPI generation from attributes

The same `#[Route]`, `#[Request]`, `#[Response]`, and `#[Doc]` attributes that drive runtime behavior also generate OpenAPI 3.0 specifications. No separate schema files to maintain — your docs always match your code.

### Multi-tier reflection caching

Route metadata resolution uses a 3-tier cache: runtime memory → persistent store (Laravel Cache / APCu) → reflection fallback. Cold-start resolves once, then subsequent requests hit the cache at **< 0.5ms**.

### Route caching with manifest tracking

`AttributeRouteCache` compiles routes into `bootstrap/cache/attribute_routes.php` with file-level tracking — only changed controllers trigger rebuilds.

```bash
php artisan zolta:routes:cache    # Build route cache
php artisan zolta:routes:clear    # Clear route cache
php artisan zolta:routes:watch    # Watch for file changes in development
```

### Exception handling

The `HandlesApiExceptions` trait normalizes all exceptions into the standard response envelope. Domain exceptions (`ValidationException`, `NotFoundException`, `ConflictException`) carry structured error data; framework exceptions are safely wrapped.

### File upload support

`UploadedFileDTO` provides a framework-neutral representation with `clientOriginalName`, `clientMimeType`, `size`, `tmpPath`, and `error` — works identically on Laravel and Symfony.

---

## Performance

Benchmarked on a real application (Laravel 12, PHP 8.3, SQLite):

| Pipeline step | Time (warm) |
|--------------|-------------|
| RouteMetadataResolver | 1.3–1.9ms |
| RouteConfigValidator | < 0.01ms |
| ServiceInvoker overhead | < 0.5ms |
| ResourceTransformer | 0.5–0.7ms |
| ResponseFactory | 0.5–0.6ms |
| **Total HTTP pipeline overhead** | **< 2ms** |

The rest of your request time is your application logic — Eloquent queries, external calls, business rules. The transport layer stays invisible.

---

## Dual framework support

Every module has independent Laravel adapter (Symfony support is coming soon). The same controller code runs on either framework without modification.:

| Feature | Laravel | Symfony |
|---------|---------|---------|
| Attribute routing | ✅ | coming soon |
| Form request validation | ✅ | coming soon |
| DTO mapping | ✅ | coming soon |
| Authorization | ✅ | coming soon |
| Exception handling | ✅ | coming soon |
| Response shaping | ✅ | coming soon |
| View rendering | ✅ | coming soon |
| OpenAPI generation | ✅ | coming soon |
| Route caching | ✅ | coming soon |
| Reflection caching | ✅ | coming soon |

Write your controllers once, deploy on either framework.

---

## QA

```bash
composer run qa          # Full suite: lint + analyse + phpmd + rector + test
composer run test        # PHPUnit only
```

**146 tests, 323 assertions** covering routing attributes, authorization matrix, request mapping, response contracts, exception handling, caching, and security.

---

## Part of the Zolta Ecosystem

Zolta HTTP is the **transport layer** — it wires HTTP to your application through clean attributes:

```
┌─────────────────────────────────────────────┐
│  zolta/http (Transport) ← you are here      │
│  Attribute-driven routing & response        │
├─────────────────────────────────────────────┤
│  zolta/cqrs (Application)                   │
│  Commands, queries, events, transactions    │
├─────────────────────────────────────────────┤
│  zolta/forge (Domain)                       │
│  Value Objects, rules, specs, entities      │
└─────────────────────────────────────────────┘
```

When used together: a request arrives → **HTTP** resolves the pipeline via attributes → **Forge** hydrates the command with validated VOs → **CQRS** dispatches through the bus, captures events, wraps transactions → **HTTP** transforms and returns the response. All of this happens with **< 5ms of package overhead** and zero manual wiring.

| Package | Layer | Link |
|---------|-------|------|
| zolta/forge | Domain | [`packages/forge`](../zolta-forge) |
| zolta/cqrs | Application | [`packages/cqrs`](../zolta-cqrs) |
| **zolta/http** | **Transport** | You are here |

---

## Documentation

Full documentation is available in the [`docs/`](./docs/) directory, organized for serving via Nuxt Content.

## License

[MIT](LICENSE) © 2026 Redouane Taleb
