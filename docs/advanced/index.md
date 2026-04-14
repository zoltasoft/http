---
title: Advanced
description: Advanced topics including framework bridge architecture, Symfony adapters, and QA tooling.
navigation:
  title: Advanced
  order: 3
---

# Advanced

## Framework bridge pattern

Zolta HTTP achieves framework independence through a **bridge pattern** powered by `class_alias` and a `FrameworkRegistry`.

### How it works

1. At boot time, the framework adapter registers its implementations:

```php
// Laravel adapter registers:
FrameworkRegistry::registerBinding(Controller::class, LaravelController::class);
FrameworkRegistry::registerBinding(BaseRequest::class, LaravelBridgeRequest::class);
```

2. The agnostic base classes resolve their parent at load time:

```php
// In Controller.php
$binding = FrameworkRegistry::resolveBinding(Controller::class);
class_alias($binding, 'FrameworkBoundController');

class Controller extends FrameworkBoundController {}
```

3. Application code extends the agnostic class:

```php
final class UserController extends Controller
{
    // Gets Laravel or Symfony behavior transparently
}
```

### Why class_alias?

PHP does not support dynamic inheritance. `class_alias` is the only mechanism that allows a class to extend different parents depending on runtime conditions. This is central to the dual-framework architecture.

### Implications

- Static analysis tools (PHPStan) cannot fully resolve the inheritance chain — some ignoreErrors are needed
- Rector may incorrectly privatize methods that are overridden through the bridge — `@noRector` annotations protect these
- Tests use concrete implementations or anonymous classes to test contracts

## Symfony adapter

The Symfony adapter is a full-featured integration under `src/Adapters/Symfony/`:

```
Adapters/Symfony/
├── Bootstrap/
│   ├── Discovery/       # Controller and route discovery
│   ├── Execution/       # Request execution pipeline
│   ├── Metadata/        # Attribute metadata extraction
│   └── Routing/         # Symfony route registration
├── Console/
│   └── Command/         # Symfony console commands
├── Controllers/         # Symfony controller adapters
├── DependencyInjection/
│   ├── Compiler/        # DI compiler passes
│   ├── Registrar/       # Service registration helpers
│   └── Support/         # Container support utilities
├── Exceptions/          # Symfony exception rendering
├── Requests/
│   └── Bridge/          # Symfony request bridge
├── Services/
│   ├── Locator/         # Service locator
│   └── Serialization/   # Response serialization
└── Support/             # Utility classes
```

### Compiler passes

The Symfony adapter uses compiler passes for service registration:

- **Core compiler**: Registers core services (router, request bridge, response bridge)
- **Behavior compiler**: Registers middleware and authorization behaviors
- **Adapter compiler**: Registers framework-specific adapter bindings

### Route discovery

Symfony routes are discovered by scanning controller directories configured in the bundle:

```yaml
# config/packages/zolta_http.yaml
zolta_http:
    controllers:
        - App\Services\*\API\Controllers
```

## QA pipeline

The package includes a comprehensive QA pipeline:

| Tool | Command | Purpose |
|------|---------|---------|
| Pint | `composer run lint` | Code style (PSR-12) |
| PHPStan | `composer run analyse` | Static analysis (level 6) |
| PHPMD | `composer run phpmd` | Mess detection |
| Rector | `composer run rector` | Automated refactoring |
| PHPUnit | `composer run test` | Unit tests |
| **All** | `composer run qa` | Runs all of the above |

### PHPStan configuration

PHPStan is configured at level 6 with targeted ignoreErrors for:

- `class_alias` bridge patterns (unresolvable inheritance)
- Optional framework dependencies (Symfony validators)
- External package references (Zolta\Core, Zolta\Laravel)
- Iterable type declarations on adapter boundaries

### Rector configuration

Rector applies PHP 8.2+ modernization with skip rules for:

- Bridge classes (dynamic inheritance prevents Rector from seeing overrides)
- Factory arrays in DI registrars (complex array shapes)

## Namespace reference

| Namespace | Path | Purpose |
|-----------|------|---------|
| `Zolta\Http\Router` | `src/Router/` | Route registration and discovery |
| `Zolta\Http\Router\Attributes` | `src/Router/Attributes/` | Route attribute |
| `Zolta\Http\Request` | `src/Request/` | Request validation and mapping |
| `Zolta\Http\Request\Attributes` | `src/Request/Attributes/` | Request attribute |
| `Zolta\Http\Response` | `src/Response/` | Response payloads and HTTP output |
| `Zolta\Http\Response\Attributes` | `src/Response/Attributes/` | Response and View attributes |
| `Zolta\Http\Response\Resources` | `src/Response/Resources/` | Base Resource class |
| `Zolta\Http\Controller` | `src/Controller/` | Framework-agnostic controller |
| `Zolta\Http\Service` | `src/Service/` | Service binding and discovery |
| `Zolta\Http\Service\Attributes` | `src/Service/Attributes/` | Service, Doc, Resource attributes |
| `Zolta\Http\Service\DTO` | `src/Service/DTO/` | UploadedFileDTO |
| `Zolta\Http\Exceptions` | `src/Exceptions/` | Exception classes |
| `Zolta\Http\Exceptions\Traits` | `src/Exceptions/Traits/` | HandlesApiExceptions trait |
| `Zolta\Http\Authorization` | `src/Authorization/` | AuthorizationMatrix |
| `Zolta\Http\Symfony` | `src/Adapters/Symfony/` | Symfony adapter |
