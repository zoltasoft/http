---
title: Examples
description: Complete real-world examples using Zolta HTTP.
navigation:
  title: Examples
  order: 4
---

# Examples

Real-world examples from a User Management service built with Zolta HTTP.

## Authentication controller

A controller handling login, logout, and registration with token-based auth:

```php
<?php

namespace App\Services\UserManagementService\API\Controllers\auth;

use App\Services\UserManagementService\API\Requests\auth\LoginRequest;
use App\Services\UserManagementService\API\Requests\auth\RegisterRequest;
use App\Services\UserManagementService\API\Resources\auth\LoginResource;
use App\Services\UserManagementService\API\Resources\auth\RegisterResource;
use App\Services\UserManagementService\Application\DTOs\Input\auth\LoginInputDTO;
use App\Services\UserManagementService\Application\DTOs\Input\auth\RegisterInputDTO;
use App\Services\UserManagementService\Application\Services\auth\LoginService;
use App\Services\UserManagementService\Application\Services\auth\LogoutService;
use App\Services\UserManagementService\Application\Services\auth\RegisterService;
use Zolta\Http\Controller\Controller;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Service\Attributes\Service;

final class AuthenticationController extends Controller
{
    #[Route(path: 'auth/login', methods: ['POST'], middleware: ['api'], name: 'auth.login')]
    #[Request(LoginRequest::class, LoginInputDTO::class)]
    #[Service(LoginService::class, 'Request resolved successfully.', 200)]
    #[Response(LoginResource::class)]
    public function login() {}

    #[Route(path: 'auth/logout', methods: ['POST'], middleware: ['api', 'auth:sanctum'], name: 'auth.logout')]
    #[Service(LogoutService::class, 'Logged out successfully.', 200)]
    public function logout() {}

    #[Route(path: 'auth/register', methods: ['POST'], middleware: ['api'], name: 'auth.register')]
    #[Request(RegisterRequest::class, RegisterInputDTO::class)]
    #[Service(RegisterService::class, 'Registered successfully.', 201)]
    #[Response(RegisterResource::class)]
    public function register() {}
}
```

**Key points:**
- `login` and `register` have `#[Request]` (with validation) and `#[Response]` (with resource)
- `logout` has no request or response — it only needs auth middleware and a service
- Method bodies are empty — all behavior is attribute-driven

## CRUD controller with authorization

A full CRUD controller for roles with permission management:

```php
<?php

namespace App\Services\UserManagementService\API\Controllers;

use Zolta\Http\Controller\Controller;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Service\Attributes\Doc;
use Zolta\Http\Service\Attributes\Service;

final class RoleController extends Controller
{
    #[Route(path: 'roles', methods: ['GET'], middleware: ['api', 'auth:sanctum'], name: 'roles.list')]
    #[Service(ListRolesService::class, 'Request resolved successfully.', 200)]
    #[Response(RoleListResource::class)]
    #[Doc(summary: 'List all roles')]
    public function list() {}

    #[Route(path: 'roles/{id}', methods: ['GET'], middleware: ['api', 'auth:sanctum'], name: 'roles.get')]
    #[Request(GetRoleRequest::class, GetRoleInputDTO::class)]
    #[Service(GetRoleService::class, 'Request resolved successfully.', 200)]
    #[Response(RoleResource::class)]
    #[Doc(summary: 'Get a role by ID')]
    public function get() {}

    #[Route(path: 'roles', methods: ['POST'], middleware: ['api', 'auth:sanctum'], name: 'roles.create')]
    #[Request(CreateRoleRequest::class, CreateRoleInputDTO::class)]
    #[Service(CreateRoleService::class, 'Role created successfully.', 201)]
    #[Response(RoleResource::class)]
    #[Doc(summary: 'Create a new role')]
    public function create() {}

    #[Route(path: 'roles/{id}', methods: ['PUT'], middleware: ['api', 'auth:sanctum'], name: 'roles.update')]
    #[Request(UpdateRoleRequest::class, UpdateRoleInputDTO::class)]
    #[Service(UpdateRoleService::class, 'Role updated successfully.', 200)]
    #[Response(RoleResource::class)]
    #[Doc(summary: 'Update a role by ID')]
    public function update() {}

    #[Route(path: 'roles/{id}', methods: ['DELETE'], middleware: ['api', 'auth:sanctum'], name: 'roles.delete')]
    #[Request(DeleteRoleRequest::class, DeleteRoleInputDTO::class)]
    #[Service(DeleteRoleService::class, 'Role deleted successfully.', 204)]
    #[Doc(summary: 'Delete a role by ID')]
    public function delete() {}

    #[Route(path: 'roles/{roleId}/permissions/{permissionId}', methods: ['POST'], middleware: ['api', 'auth:sanctum'])]
    #[Request(AttachPermissionRequest::class, AttachPermissionInputDTO::class)]
    #[Service(AttachPermissionToRoleService::class, 'Permission attached to role.', 200)]
    #[Response(RoleResource::class)]
    #[Doc(summary: 'Attach a permission to a role')]
    public function attachPermission() {}
}
```

**Key points:**
- `list` has no `#[Request]` — no input needed
- `delete` has no `#[Response]` — 204 status returns no body
- `attachPermission` uses nested route params `{roleId}` and `{permissionId}`
- Every action has `#[Doc]` for API documentation

## Form request with route parameters

```php
<?php

namespace App\Services\UserManagementService\API\Requests;

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

**Key points:**
- `routeParams()` extracts `{id}` from the URL and merges it into validated data
- `queryOptions()` passes `include` and `strict` options to the service for eager loading
- `messages()` provides user-friendly validation error messages

## Input DTO with validation

```php
<?php

namespace App\Services\UserManagementService\Application\DTOs\Input\auth;

use App\Services\UserManagementService\Application\Attributes\Validation\Required;
use Zolta\Support\Application\Attributes\FromRequest;
use Zolta\Support\Application\DTO\Input\InputDTO;

class RegisterInputDTO extends InputDTO
{
    final public function __construct(
        #[FromRequest('name')]
        #[Required]
        public readonly string $name,

        #[FromRequest('email')]
        #[Required]
        public readonly string $email,

        #[FromRequest('password')]
        #[Required]
        public readonly string $password,

        public readonly array $options = [],
    ) {}
}
```

**Key points:**
- `#[FromRequest]` maps request fields to DTO properties
- `#[Required]` validates during DTO mapping (separate from form request rules)
- `$options` captures query options from the form request
- Constructor is `final` to prevent override issues in the mapper

## Resource with data transformation

```php
<?php

namespace App\Services\UserManagementService\API\Resources;

use Zolta\Http\Response\Resources\Resource;

final class GetUserByIdResource extends Resource
{
    public function toArray(): array
    {
        return [
            'user' => $this->all()['user'],
            'user_id' => $this->get('user')['id'],
        ];
    }
}
```

**Key points:**
- `$this->all()` returns the entire response DTO as an array
- `$this->get('user')` accesses a specific property from the DTO
- The resource controls exactly what data appears in the `data` key of the response

## Authorization with route-level abilities

```php
#[Route(
    path: 'users/{id}',
    methods: ['GET'],
    middleware: ['api'],
    auth: 'sanctum',
    authorized: ['can_manage_users'],
    name: 'users.getById',
)]
#[Request(GetUserByIdRequest::class, GetUserByIdDTO::class)]
#[Service(GetUserByIdService::class, 'The user was found successfully.', 200)]
#[Response(GetUserByIdResource::class)]
#[Doc(summary: 'Get a user by ID')]
public function getUserById() {}
```

With this configuration:

```php
AuthorizationMatrix::configure([
    'abilities' => [
        'can_manage_users' => ['users.read', 'users.write'],
    ],
    'user' => [
        'class' => \App\Models\User::class,
        'attributes' => ['permissions', 'role.permissions'],
    ],
]);
```

The user must have both `users.read` and `users.write` permissions (resolved from their `permissions` or `role.permissions`) to access this endpoint.

## API response examples

### Successful response

```bash
curl -X GET http://localhost:8000/api/users/123 \
  -H "Authorization: Bearer <token>"
```

```json
{
    "success": true,
    "message": "The user was found successfully.",
    "data": {
        "user": {
            "id": "123",
            "name": "John Doe",
            "email": "john@example.com",
            "role": {
                "id": 1,
                "name": "admin",
                "permissions": ["users.read", "users.write"]
            }
        },
        "user_id": "123"
    },
    "errors": [],
    "debug": []
}
```

### Validation error

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{}'
```

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "data": [],
    "errors": {
        "email": ["The email is required."],
        "password": ["The password is required."]
    },
    "debug": []
}
```

### Authorization error

```bash
curl -X DELETE http://localhost:8000/api/users/123 \
  -H "Authorization: Bearer <token-without-permissions>"
```

```json
{
    "success": false,
    "message": "You do not have permission to perform this action.",
    "data": [],
    "errors": [],
    "debug": []
}
```
