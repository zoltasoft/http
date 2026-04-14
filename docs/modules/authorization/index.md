---
title: Authorization
description: Ability-to-permission mapping, user resolution, and route-level access control.
navigation:
  title: Authorization
  order: 7
---

# Authorization

The Authorization module provides a centralized, configuration-driven system for mapping abilities to permissions and enforcing access control on routes.

## AuthorizationMatrix

```php
use Zolta\Http\Authorization\AuthorizationMatrix;
```

The `AuthorizationMatrix` is a static registry that maps named abilities to required permissions, resolves user permissions from configurable paths, and checks whether a user is granted a specific ability.

### Configuration

```php
AuthorizationMatrix::configure([
    'abilities' => [
        'can_manage_users' => ['users.read', 'users.write', 'users.delete'],
        'can_view_reports' => ['reports.read'],
        'can_manage_roles' => ['roles.read', 'roles.write'],
    ],
    'user' => [
        'class' => \App\Models\User::class,
        'attributes' => [
            'permissions',
            'role.permissions',
            'roles.*.permissions',
        ],
    ],
]);
```

### Configuration shape

| Key | Type | Description |
|-----|------|-------------|
| `abilities` | `array<string, string[]>` | Maps ability names to required permission strings |
| `user.class` | `string` | User model class (enforced at runtime) |
| `user.attributes` | `string[]` | Dot-paths to extract permissions from the user object |

### Permission resolution paths

The `user.attributes` array defines dot-notation paths for extracting permissions from the user object. Supports wildcards:

| Path | Description |
|------|-------------|
| `permissions` | Direct permissions on the user |
| `role.permissions` | Permissions from a single role relation |
| `roles.*.permissions` | Permissions from all roles (wildcard expands arrays) |

For a path like `roles.*.permissions`, the matrix iterates over all items in the `roles` array, extracting `permissions` from each.

## Public API

### `configure(array $config): void`

Set the full configuration (abilities + user settings):

```php
AuthorizationMatrix::configure($config);
```

### `setAbilities(array $abilities): void`

Set only the abilities map:

```php
AuthorizationMatrix::setAbilities([
    'can_publish' => ['articles.write', 'articles.publish'],
]);
```

### `setUserAttributes(array $attributes): void`

Set only the user permission paths:

```php
AuthorizationMatrix::setUserAttributes([
    'permissions',
    'role.permissions',
]);
```

### `setUserResolver(UserResolverInterface $resolver): void`

Set the resolver for the authenticated user:

```php
AuthorizationMatrix::setUserResolver($myResolver);
```

### `isGranted(string|array $abilities, ?UserInterface $user = null): bool`

Check if a user has all required permissions for the given abilities:

```php
if (AuthorizationMatrix::isGranted('can_manage_users')) {
    // Current user has all permissions for can_manage_users
}

if (AuthorizationMatrix::isGranted(['can_manage_users', 'can_view_reports'], $user)) {
    // Specific user has permissions for both abilities
}
```

### `requiredPermissions(string|array $abilities): array`

Get the flat list of permissions required for given abilities:

```php
$permissions = AuthorizationMatrix::requiredPermissions('can_manage_users');
// ['users.read', 'users.write', 'users.delete']
```

### `permissionsForUser(?UserInterface $user): array`

Get all permissions for a user by traversing configured attribute paths:

```php
$permissions = AuthorizationMatrix::permissionsForUser($user);
// ['users.read', 'users.write', 'reports.read', ...]
```

### `getCurrentUser(): ?UserInterface`

Get the currently authenticated user:

```php
$user = AuthorizationMatrix::getCurrentUser();
```

### `isConfigured(): bool`

Check if the matrix has been configured:

```php
if (AuthorizationMatrix::isConfigured()) {
    // ...
}
```

## Route-level authorization

Use the `authorized` parameter on `#[Route]` to enforce abilities:

```php
#[Route(
    path: 'users/{id}',
    methods: ['DELETE'],
    auth: 'sanctum',
    authorized: ['can_manage_users'],
)]
public function delete() {}
```

When the route is matched:

1. The authenticated user is resolved
2. `AuthorizationMatrix::isGranted('can_manage_users', $user)` is called
3. If `false`, an `ActionNotAllowedException` (403) is thrown

### Multiple abilities

```php
#[Route(
    path: 'admin/settings',
    methods: ['PUT'],
    auth: 'sanctum',
    authorized: ['can_manage_users', 'can_manage_roles'],
)]
public function updateSettings() {}
```

The user must satisfy **all** listed abilities.

## Request-level authorization

Use `authorizeAction()` in form requests for fine-grained control:

```php
final class DeleteUserRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $this->authorizeAction('can_manage_users');
        return true;
    }
}
```

## Laravel integration

The Authorization module integrates with Laravel through:

- `AuthorizationServiceProvider` — Registers the user resolver and loads abilities from config
- Config file published to `config/zolta-authorization.php`

```php
// config/zolta-authorization.php
return [
    'abilities' => [
        'can_manage_users' => ['users.read', 'users.write'],
    ],
    'user' => [
        'class' => App\Models\User::class,
        'attributes' => ['permissions', 'role.permissions'],
    ],
];
```

## Symfony integration

The Symfony adapter registers the authorization matrix through the DI container and loads configuration from the bundle config.

## User interface

The `UserInterface` contract defines the minimum user shape:

```php
interface UserInterface
{
    public function getIdentifier(): string|int;
}
```

The `UserResolverInterface` provides the authenticated user:

```php
interface UserResolverInterface
{
    public function resolve(): ?UserInterface;
}
```
