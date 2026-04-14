---
title: Getting Started
description: Install and configure Zolta HTTP in your Laravel or Symfony application.
navigation:
  title: Getting Started
  order: 1
---

# Getting Started

## Installation

Install via Composer:

```bash
composer require zolta/http
```

### Laravel

The package auto-discovers `ZoltaHttpServiceProvider`. No manual registration is needed.

The provider registers:

- Route discovery and registration from controller attributes
- Request bridge bindings for validation and DTO mapping
- Response bridge for standardized output
- Authorization matrix configuration
- Artisan commands for route caching and service discovery

### Symfony

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Zolta\Http\Symfony\ZoltaHttpBundle::class => ['all' => true],
];
```

The Symfony adapter provides compiler passes for service registration, route discovery, and dependency injection.

## Project structure

A typical application using Zolta HTTP follows a service-oriented structure:

```
app/
└── Services/
    └── UserManagementService/
        ├── API/
        │   ├── Controllers/
        │   │   └── UserController.php
        │   ├── Requests/
        │   │   └── GetUserRequest.php
        │   └── Resources/
        │       └── UserResource.php
        └── Application/
            ├── DTOs/
            │   ├── Input/
            │   │   └── GetUserDTO.php
            │   └── Output/
            │       └── UserResponseDTO.php
            └── Services/
                └── GetUserService.php
```

Each service domain has its own API layer (controllers, requests, resources) and application layer (DTOs, services).

## Minimal example

### 1. Create a controller

```php
<?php

namespace App\Services\BookService\API\Controllers;

use App\Services\BookService\API\Requests\ListBooksRequest;
use App\Services\BookService\API\Resources\BookListResource;
use App\Services\BookService\Application\Services\ListBooksService;
use Zolta\Http\Controller\Controller;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Response\Attributes\Response;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Service\Attributes\Service;

final class BookController extends Controller
{
    #[Route(path: 'books', methods: ['GET'], middleware: ['api'])]
    #[Service(ListBooksService::class, 'Books retrieved.', 200)]
    #[Response(BookListResource::class)]
    public function list() {}
}
```

### 2. Create a form request

```php
<?php

namespace App\Services\BookService\API\Requests;

use Zolta\Http\Request\BaseRequest;

final class ListBooksRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
```

### 3. Create a service

Your service class receives the validated input (or DTO) and returns a response DTO:

```php
<?php

namespace App\Services\BookService\Application\Services;

class ListBooksService
{
    public function __invoke($input = null)
    {
        $books = Book::all();

        return new BookListResponseDTO(books: $books->toArray());
    }
}
```

### 4. Create a resource

```php
<?php

namespace App\Services\BookService\API\Resources;

use Zolta\Http\Response\Resources\Resource;

final class BookListResource extends Resource
{
    public function toArray(): array
    {
        return [
            'books' => $this->all()['books'],
        ];
    }
}
```

### 5. Result

A `GET /api/books` request returns:

```json
{
    "success": true,
    "message": "Books retrieved.",
    "data": {
        "books": [
            { "id": 1, "title": "Domain-Driven Design" },
            { "id": 2, "title": "Clean Architecture" }
        ]
    },
    "errors": [],
    "debug": []
}
```

## Next steps

- [Router module](/modules/router) — Route attributes, middleware, and authentication
- [Request module](/modules/request) — Validation rules, DTO mapping, and form requests
- [Response module](/modules/response) — Response payloads, resources, and HTTP responses
- [Full examples](/examples) — Complete real-world controller examples
