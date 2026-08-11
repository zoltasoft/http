---
title: Identity
description: Remote Identity token introspection for Laravel applications.
navigation:
  title: Identity
  order: 8
---

# Identity

The Identity module validates bearer tokens with a Zolta Identity service and exposes the validated identity to Laravel requests.

## Configure a connection

Publish the configuration, then provide credentials for at least one connection:

```bash
php artisan vendor:publish --tag=identity-consumer-config
```

```dotenv
IDENTITY_API_URL=https://identity.example.test
IDENTITY_PROJECT=my-project
IDENTITY_CLIENT_ID=my-service
IDENTITY_CLIENT_SECRET=secret
```

The published configuration supports separate `live` and `sandbox` connections. Set `IDENTITY_SANDBOX_*` variables when your application needs a sandbox connection. The `project` value is optional; when present, the returned `project_id` or `project_slug` must match it.

Identity settings are read from `zolta.identity_consumer`. The legacy `identity-consumer` configuration key remains supported for existing applications.

## Protect a route

The package registers the `identity.introspect` middleware alias. Add it to a route directly, or include an optional required permission as its parameter:

```php
Route::get('/profile', ProfileController::class)
    ->middleware('identity.introspect');

Route::get('/admin/reports', ReportController::class)
    ->middleware('identity.introspect:reports.read');
```

When validation succeeds, the middleware sets the authenticated Laravel user to an `IdentityPrincipal` and stores the full `IntrospectedIdentity` value on the request under `identity`:

```php
$identity = $request->attributes->get('identity');
$userId = $identity->userId;
```

Missing or inactive tokens receive `401`, a missing required permission receives `403`, and an unavailable Identity service receives `503`.

## Caching

Successful introspection responses are cached using a SHA-256 token hash. `IDENTITY_INTROSPECTION_CACHE_SECONDS` defaults to 30 seconds; the effective TTL never exceeds the token's `exp` claim.

## Verify Identity webhooks

Use `WebhookSignatureVerifier` to verify `v1=` HMAC-SHA256 signatures before processing an Identity webhook:

```php
use Zolta\Http\Identity\Laravel\Webhooks\WebhookSignatureVerifier;

$verified = app(WebhookSignatureVerifier::class)->verify(
    payload: $request->getContent(),
    timestamp: $request->header('X-Identity-Timestamp', ''),
    signature: $request->header('X-Identity-Signature', ''),
    secrets: config('zolta.identity_consumer.webhook_secrets', []),
    toleranceSeconds: config('zolta.identity_consumer.webhook_tolerance_seconds', 300),
);
```
