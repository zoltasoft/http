<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use Zolta\Http\Identity\Laravel\Exceptions\IdentityServiceUnavailable;

final class IdentityIntrospector
{
    public function introspect(string $token): ?IntrospectedIdentity
    {
        $cacheKey = 'zolta-identity:introspection:'.hash('sha256', $token);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return IntrospectedIdentity::fromPayload($cached['payload'], $cached['connection']);
        }

        $configured = 0;
        $unavailable = 0;
        foreach ((array) config('zolta.identity_consumer.connections', config('identity-consumer.connections', [])) as $name => $connection) {
            if (! $this->configured($connection)) {
                continue;
            }
            $configured++;

            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('zolta.identity_consumer.timeout_seconds', config('identity-consumer.timeout_seconds', 5)))
                    ->post(rtrim((string) $connection['base_url'], '/').'/api/v1/identity/auth/introspect', [
                        'client_id' => $connection['client_id'],
                        'client_secret' => $connection['client_secret'],
                        'token' => $token,
                    ]);
            } catch (Throwable) {
                $unavailable++;

                continue;
            }

            if (! $response->successful()) {
                $unavailable++;

                continue;
            }

            $payload = $response->json();
            if (! is_array($payload) || ($payload['active'] ?? false) !== true) {
                continue;
            }
            if (! $this->matchesProject($payload, (string) ($connection['project'] ?? ''))) {
                continue;
            }

            $ttl = min(
                (int) config('zolta.identity_consumer.cache_seconds', config('identity-consumer.cache_seconds', 30)),
                max(1, (int) ($payload['exp'] ?? now()->addSecond()->getTimestamp()) - now()->getTimestamp()),
            );
            Cache::put($cacheKey, ['payload' => $payload, 'connection' => (string) $name], $ttl);

            return IntrospectedIdentity::fromPayload($payload, (string) $name);
        }

        if ($configured === 0 || $unavailable === $configured) {
            throw new IdentityServiceUnavailable('No configured Identity connection could validate the token.');
        }

        return null;
    }

    private function configured(mixed $connection): bool
    {
        return is_array($connection)
            && (string) ($connection['base_url'] ?? '') !== ''
            && (string) ($connection['client_id'] ?? '') !== ''
            && (string) ($connection['client_secret'] ?? '') !== '';
    }

    /** @param array<string, mixed> $payload */
    private function matchesProject(array $payload, string $expected): bool
    {
        return in_array($expected, ['', (string) ($payload['project_id'] ?? ''), (string) ($payload['project_slug'] ?? '')], true);
    }
}
