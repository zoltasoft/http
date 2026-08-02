<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Zolta\Http\Request\Laravel\HttpQueryOptions;
use Zolta\Http\Request\Laravel\RequestMapper;

final class LaravelRequestTrustBoundaryTest extends TestCase
{
    public function test_trusted_data_overrides_client_controlled_values(): void
    {
        $request = new class extends FormRequest
        {
            /** @return array<string, mixed> */
            public function validated($key = null, $default = null): mixed
            {
                $data = ['user_id' => 'attacker', 'title' => 'Candidate'];

                return $key === null ? $data : data_get($data, $key, $default);
            }

            /** @return array<string, mixed> */
            public function trustedData(): array
            {
                return ['user_id' => 'authenticated-user'];
            }

            public function optionsPayload(): ?array
            {
                return null;
            }
        };

        $this->assertSame([
            'user_id' => 'authenticated-user',
            'title' => 'Candidate',
        ], RequestMapper::map($request));
    }

    public function test_legacy_with_data_remains_trusted_during_migration(): void
    {
        $request = new class extends FormRequest
        {
            /** @return array<string, mixed> */
            public function validated($key = null, $default = null): mixed
            {
                $data = ['user_id' => 'attacker', 'title' => 'Candidate'];

                return $key === null ? $data : data_get($data, $key, $default);
            }

            /** @return array<string, mixed> */
            public function withData(): array
            {
                return ['user_id' => 'authenticated-user'];
            }
        };

        $this->assertSame([
            'user_id' => 'authenticated-user',
            'title' => 'Candidate',
        ], RequestMapper::map($request));
    }

    public function test_query_context_is_only_accepted_from_server_configuration(): void
    {
        $request = Request::create('/things', 'GET', [
            'context' => ['tenant_id' => 'attacker'],
        ]);

        $payload = HttpQueryOptions::payload($request, [
            'context' => ['tenant_id' => 'trusted-tenant'],
        ]);

        $this->assertSame(['tenant_id' => 'trusted-tenant'], $payload['context']);
    }
}
