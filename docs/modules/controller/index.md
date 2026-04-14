---
title: Controller
description: Framework-agnostic base controller using the bridge pattern.
navigation:
  title: Controller
  order: 4
---

# Controller

The Controller module provides a framework-agnostic base class that all API controllers extend. It uses a bridge pattern to adapt the controller to the active framework at runtime.

## Usage

```php
use Zolta\Http\Controller\Controller;

final class BookController extends Controller
{
    #[Route(path: 'books', methods: ['GET'])]
    #[Service(ListBooksService::class)]
    #[Response(BookListResource::class)]
    public function list() {}
}
```

## How it works

The `Controller` class is a thin shell that resolves to a framework-specific implementation at runtime:

```
Controller (class_alias → FrameworkBoundController)
    │
    ├── Laravel: Extends Illuminate\Routing\Controller
    │
    └── Symfony: Extends Symfony\Bundle\FrameworkBundle\Controller\AbstractController
```

The resolution happens via `FrameworkRegistry::resolveBinding(Controller::class)`:

1. The framework adapter registers its controller implementation at boot time
2. `class_alias` binds `FrameworkBoundController` to the resolved class
3. `Controller` extends `FrameworkBoundController`

This means your controllers get full access to framework features (dependency injection, middleware, etc.) while remaining portable between frameworks.

## Empty method bodies

Controller methods have **empty bodies by design**. All behavior is declared through attributes:

```php
// ✅ Correct — attributes drive everything
#[Route(path: 'users/{id}', methods: ['GET'])]
#[Request(GetUserRequest::class, GetUserDTO::class)]
#[Service(GetUserService::class, 'User found.', 200)]
#[Response(UserResource::class)]
public function show() {}

// ❌ Wrong — do not put logic in controller methods
public function show(Request $request)
{
    $user = User::find($request->id);
    return response()->json($user);
}
```

The framework reads these attributes to wire:
1. Route registration and middleware
2. Request validation and DTO mapping
3. Service invocation
4. Response transformation

## Final classes

Controllers should be declared `final` to prevent inheritance-based coupling:

```php
final class UserController extends Controller
{
    // ...
}
```

## Framework fallback

If no framework implementation is registered, the controller falls back to `FrameworkControllerFallback`, which provides a minimal base class for testing or framework-independent usage.
