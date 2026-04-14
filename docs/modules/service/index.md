---
title: Service
description: Service layer binding, documentation attributes, and file upload DTOs.
navigation:
  title: Service
  order: 5
---

# Service

The Service module binds controller actions to service classes, provides API documentation metadata, and handles file upload DTOs.

## The Service attribute

```php
use Zolta\Http\Service\Attributes\Service;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Service
{
    public function __construct(
        public string $class,
        public string $successMessage = 'Request completed successfully.',
        public int $status = 200,
    ) {}
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `class` | `string` | — | Service class to invoke |
| `successMessage` | `string` | `'Request completed successfully.'` | Message for successful responses |
| `status` | `int` | `200` | HTTP status code on success |

### Usage

```php
#[Service(CreateUserService::class, 'User created.', 201)]
public function create() {}

#[Service(DeleteUserService::class, 'User deleted.', 204)]
public function delete() {}
```

The framework resolves the service class from the container, invokes it with the validated input (or DTO), and wraps the result in a `ResponsePayload` with the configured message and status.

## The Doc attribute

```php
use Zolta\Http\Service\Attributes\Doc;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Doc
{
    public function __construct(
        public string $summary = '',
        public string $description = '',
        public array $tags = [],
    ) {}
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `summary` | `string` | `''` | Short summary for API documentation |
| `description` | `string` | `''` | Detailed endpoint description |
| `tags` | `array` | `[]` | OpenAPI tags for grouping |

### Usage

```php
#[Doc(
    summary: 'Get a user by ID',
    description: 'Retrieves a single user record with optional role inclusion.',
    tags: ['Users', 'Admin'],
)]
public function show() {}
```

The `Doc` attribute metadata can be extracted at build time to generate OpenAPI specifications.

## The Resource attribute

```php
use Zolta\Http\Service\Attributes\Controller\Resource;
```

### Signature

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Resource
{
    public function __construct(
        public string $class,
    ) {}
}
```

Used for service-level resource binding when the response resource differs from the standard `#[Response]` flow.

## UploadedFileDTO

For file uploads, the `UploadedFileDTO` provides a framework-agnostic representation:

```php
use Zolta\Http\Service\DTO\UploadedFileDTO;
```

### Structure

```php
readonly class UploadedFileDTO
{
    public function __construct(
        public string $clientOriginalName,
        public ?string $clientMimeType = null,
        public ?int $size = null,
        public ?string $tmpPath = null,
        public ?string $error = null,
    ) {}

    public function toArray(): array;
}
```

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `clientOriginalName` | `string` | Original filename as uploaded |
| `clientMimeType` | `?string` | MIME type (e.g., `image/png`) |
| `size` | `?int` | File size in bytes |
| `tmpPath` | `?string` | Temporary file path on disk |
| `error` | `?string` | Upload error message, if any |

### Example

```php
// In a service class
public function __invoke(CreateDocumentDTO $input)
{
    /** @var UploadedFileDTO $file */
    $file = $input->file;

    Storage::putFileAs(
        'documents',
        $file->tmpPath,
        $file->clientOriginalName,
    );

    return new DocumentResponseDTO(
        name: $file->clientOriginalName,
        size: $file->size,
    );
}
```

## Service provider

### Laravel

The `ZoltaHttpServiceProvider` registers:

- Service discovery and container bindings
- Artisan commands for service caching
- View composers and email templates
- Configuration publishing

```php
// Published config
php artisan vendor:publish --tag=zolta-http-config
```

### Symfony

Services are registered through the DI compiler passes in `src/Adapters/Symfony/DependencyInjection/`.

## Complete example

```php
use Zolta\Http\Controller\Controller;
use Zolta\Http\Router\Attributes\Route;
use Zolta\Http\Request\Attributes\Request;
use Zolta\Http\Service\Attributes\Service;
use Zolta\Http\Service\Attributes\Doc;
use Zolta\Http\Response\Attributes\Response;

final class PermissionController extends Controller
{
    #[Route(path: 'permissions', methods: ['GET'], middleware: ['api', 'auth:sanctum'])]
    #[Service(ListPermissionsService::class, 'Request resolved successfully.', 200)]
    #[Response(PermissionListResource::class)]
    public function list() {}

    #[Route(path: 'permissions', methods: ['POST'], middleware: ['api', 'auth:sanctum'])]
    #[Request(CreatePermissionRequest::class, CreatePermissionInputDTO::class)]
    #[Service(CreatePermissionService::class, 'Permission created successfully.', 201)]
    #[Response(PermissionResource::class)]
    public function create() {}

    #[Route(path: 'permissions/{id}', methods: ['DELETE'], middleware: ['api', 'auth:sanctum'])]
    #[Request(DeletePermissionRequest::class, DeletePermissionInputDTO::class)]
    #[Service(DeletePermissionService::class, 'Permission deleted successfully.', 204)]
    public function delete() {}
}
```
