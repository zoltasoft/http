# Zolta HTTP — Router

This submodule provides **attribute-based routing** and route discovery for Zolta Forge.
It is part of the ongoing extraction from the legacy HTTP layer into independent concepts.

## Objective

- Offer a framework-agnostic router API built around attributes.
- Provide adapters for each framework (Laravel first, Symfony next).
- Support fast discovery + caching of attribute routes.

## Intent

- Keep routing concerns isolated from Request/Response.
- Let each framework adapter map to its native routing system.

## Current implementation status

- ✅ **Laravel adapter extracted** (`Adapters/Laravel/...`).
  - Attribute route discovery and cache builder are implemented.
  - `RouteInvoker` exists but still contains **commented/partial features**.
- ❌ **Symfony adapter not yet extracted** (still in legacy `src/Adapters/Symfony`).

## Notes / Next steps

- Finish the Laravel RouteInvoker pipeline.
- Extract Symfony routing into `src/Router/Adapters/Symfony`.

## Controller Execution Model — Attribute-Based Routing

This framework uses attributes to define how an HTTP request is handled. Attributes may be applied at the controller class level or at the method level.

### Supported Attributes

- **Route** — defines which action is executed for an endpoint.
- **Request** — defines validation and optional DTO mapping.
- **Service** — defines the service to execute.
- **Response** — defines how the result is returned.

### 1. The Route Attribute (Entry Point)

The `Route` attribute is mandatory. It determines which action is executed when an endpoint is hit.

#### Class-Level Route

When a Route is applied to a controller class, the controller must satisfy one of the following conditions:

- The controller defines an `__invoke()` method → the pipeline will execute this method.
- A `Service` attribute is defined at the class level → the service will be executed automatically.

If neither condition is met, this is an invalid controller configuration and an error must be thrown.

#### Method-Level Route

When a Route is applied to a controller method:

- The method represents a single endpoint action.
- The method name is irrelevant.
- The method may be:
  - **non-empty** → executed normally.
  - **empty** → execution is driven entirely by attributes.

If the method is empty, it must define:

- a `Service` attribute (what to execute)
- a `Response` attribute (what to return)

Otherwise, the configuration is invalid.

### 2. Attribute Scope Rules

All attributes (`Request`, `Service`, `Response`) may be applied at the class level or the method level.

#### Resolution Priority

- Method-level attributes override class-level attributes.
- Class-level attributes act as defaults for all routed methods.

### 3. The Request Attribute (Validation & Mapping)

The `Request` attribute defines how input data is handled. It accepts:

- a Request class (required)
- a DTO class (optional)

#### Behavior

- The Request class validates incoming data.
- If no DTO is provided: validated data is passed as an array.
- If a DTO is provided: validated data is mapped into the DTO and the DTO instance is passed forward.

#### Service Injection Rule

- If the service type-hints the DTO: the DTO instance is automatically injected.
- If no DTO exists: the validated data array is injected instead.

#### Class-Level Request

- Applies to all routed methods in the controller.
- Multiple endpoints may share the same request definition.

#### Method-Level Request

- Applies only to that endpoint.
- Overrides any class-level request configuration.

### 4. The Service Attribute (Execution)

The `Service` attribute defines what logic is executed. It may be applied at the class or method level and is mandatory when:

- the controller method is empty, or
- the controller has no `__invoke()` method.

The resolved service is executed after request validation.

### 5. The Response Attribute (Output)

The `Response` attribute defines how the result of the execution is returned. It is required when:

- execution is attribute-driven, and
- no controller logic explicitly returns a response.

The response handler receives the service result and produces the final HTTP response.
