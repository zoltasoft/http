<?php

declare(strict_types=1);

namespace Zolta\Tests\Integration\Identity;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Zolta\Http\Identity\Laravel\Exceptions\IdentityServiceUnavailable;
use Zolta\Http\Identity\Laravel\IdentityIntrospector;
use Zolta\Http\Identity\Laravel\Providers\ZoltaIdentityServiceProvider;

final class IdentityIntrospectorTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ZoltaIdentityServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('zolta.identity_consumer', [
            'connections' => [
                'live' => [
                    'base_url' => 'https://identity.example.test',
                    'project' => 'project-123',
                    'client_id' => 'consumer-client',
                    'client_secret' => 'consumer-secret',
                ],
            ],
            'timeout_seconds' => 5,
            'cache_seconds' => 30,
        ]);
    }

    public function test_it_introspects_a_project_token_and_caches_the_result(): void
    {
        Http::fake([
            'https://identity.example.test/*' => Http::response($this->activePayload(), 200),
        ]);

        $introspector = $this->app->make(IdentityIntrospector::class);

        $first = $introspector->introspect('access-token');
        $second = $introspector->introspect('access-token');

        $this->assertNotNull($first);
        $this->assertSame('user-123', $first->userId);
        $this->assertSame('reports.read', $first->permissions[0]);
        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://identity.example.test/api/v1/identity/auth/introspect'
                && $request['client_id'] === 'consumer-client'
                && $request['client_secret'] === 'consumer-secret'
                && $request['token'] === 'access-token';
        });
    }

    public function test_it_rejects_active_tokens_for_another_project(): void
    {
        Http::fake([
            'https://identity.example.test/*' => Http::response($this->activePayload(['project_id' => 'other-project']), 200),
        ]);

        $identity = $this->app->make(IdentityIntrospector::class)->introspect('access-token');

        $this->assertNull($identity);
    }

    public function test_it_reports_an_unavailable_identity_service(): void
    {
        Http::fake([
            'https://identity.example.test/*' => Http::response(['message' => 'Unavailable'], 503),
        ]);

        $this->expectException(IdentityServiceUnavailable::class);

        $this->app->make(IdentityIntrospector::class)->introspect('access-token');
    }

    /** @param array<string, mixed> $overrides */
    private function activePayload(array $overrides = []): array
    {
        return array_replace([
            'active' => true,
            'sub' => 'user-123',
            'email' => 'person@example.test',
            'username' => 'person',
            'project_id' => 'project-123',
            'client_id' => 'identity-client',
            'permissions' => ['reports.read'],
            'exp' => now()->addMinutes(10)->getTimestamp(),
        ], $overrides);
    }
}
