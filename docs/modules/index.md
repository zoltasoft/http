---
title: Modules
description: Overview of all Zolta HTTP modules.
navigation:
  title: Modules
  order: 2
---

# Modules

Zolta HTTP is organized into independent modules. Each module handles a specific concern in the HTTP request lifecycle and provides its own framework adapters.

## Request lifecycle

```
Incoming HTTP Request
       │
       ▼
   ┌───────┐
   │ Route  │  Route matching, middleware, auth guards
   └───┬───┘
       ▼
   ┌─────────┐
   │ Request  │  Validation, DTO mapping
   └────┬────┘
       ▼
   ┌─────────┐
   │ Service  │  Business logic execution
   └────┬────┘
       ▼
   ┌──────────┐
   │ Response  │  Resource transformation, JSON envelope
   └──────────┘
```

## Module index

| Module | Description |
|--------|-------------|
| [Router](/modules/router) | Attribute-based route registration with middleware and auth |
| [Request](/modules/request) | Form requests, validation rules, and input DTO mapping |
| [Response](/modules/response) | Response payloads, resource transformation, and HTTP output |
| [Controller](/modules/controller) | Framework-agnostic base controller with bridge pattern |
| [Service](/modules/service) | Service class binding, documentation attributes, file uploads |
| [Exceptions](/modules/exceptions) | Exception handling, normalization, and API error responses |
| [Authorization](/modules/authorization) | Ability-to-permission mapping and user resolution |
| [Identity](/modules/identity) | Remote Identity token introspection and authenticated Laravel principals |
