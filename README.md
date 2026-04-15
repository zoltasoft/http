# Zolta HTTP

Domain-driven HTTP framework for PHP 8.2+. Attribute-based routing, automatic request validation & DTO mapping, standardized response shaping, and built-in authorization — with first-class Laravel and Symfony adapters.

## Install

```bash
composer require zolta/http
```

Laravel auto-discovers the service provider. For Symfony, register the bundle in `config/bundles.php`.

## Architecture

The package is organized into independent modules, each with its own framework adapters:

```
src/
├── Router/           # Attribute-based routing (#[Route])
├── Request/          # Validation, DTO mapping (#[Request])
├── Response/         # Response shaping, resources (#[Response])
├── Controller/       # Framework-agnostic base controller
├── Service/          # Service layer binding (#[Service], #[Doc])
├── Exceptions/       # Exception handling (HandlesApiExceptions)
├── Authorization/    # Ability/permission matrix
└── Adapters/Symfony/ # Symfony-specific bootstrap & DI
```

Each module under `Router/`, `Request/`, `Response/`, `Service/` contains an `Adapters/Laravel/` sub-directory for Laravel-specific implementations.

## Quick start

### 1. Define a controller

Controllers are empty method bodies decorated with attributes. The framework handles validation, service invocation, and response shaping automatically.

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

Configure the `AuthorizationMatrix` to map abilities to permissions:

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

Then use `authorized: ['can_manage_users']` in your `#[Route]` attribute.

## Response format

All responses follow a consistent envelope:

```json
{
    "success": true,
    "message": "User found.",
    "data": { "user": { "id": "123", "name": "John" } },
    "errors": [],
    "debug": []
}
```

## QA

```bash
composer run lint       # Pint
composer run analyse    # PHPStan level 6
composer run phpmd      # PHPMD
composer run rector     # Rector
composer run test       # PHPUnit
composer run qa         # All of the above
```

## Ecosystem

| Package | Layer | Description |
|---------|-------|-------------|
| [zolta/forge](../zolta-forge) | Domain | Value Objects, Entities, Rules, Specifications, Policies, Invariants |
| [zolta/cqrs](../zolta-cqrs) | Application | Commands, Queries, Events, Repositories, Transactions |
| **zolta/http** | **API** | **Routing, Request/Response, Authorization** |

## Documentation

Full documentation is available in the [`docs/`](./docs/) directory, organized for serving via Nuxt Content.

## License

**Proprietary — © 2026 Redouane Taleb**
Unauthorized copying, modification, or distribution is prohibited.
