# Zolta HTTP — Response

This submodule provides **response shaping and rendering** for Zolta Forge.
It is part of the ongoing extraction from the legacy HTTP layer into independent concepts.

## Objective

- Offer a framework-agnostic response API with a shared payload envelope.
- Provide adapters for each framework (Laravel first, Symfony next).
- Keep view rendering (Blade/Inertia) and API JSON handling consistent.

## Intent

- Centralize response formatting across services and controllers.
- Keep response concerns separate from routing and request validation.

## Current implementation status

- ✅ **Laravel adapter extracted** (`Adapters/Laravel/...`).
  - JSON response + view rendering bridge are implemented.
  - Resource unwrapping is handled for API responses.
- ❌ **Symfony adapter not yet extracted** (still in legacy `src/Adapters/Symfony`).

## Notes / Next steps

- Extract Symfony response bridge into `src/Response/Adapters/Symfony`.
- Align adapter behavior with `ResponsePayload` conventions.
