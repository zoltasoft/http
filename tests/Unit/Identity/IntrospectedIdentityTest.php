<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Identity;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Identity\Laravel\IntrospectedIdentity;

final class IntrospectedIdentityTest extends TestCase
{
    public function test_it_projects_claims_and_checks_permissions(): void
    {
        $identity = IntrospectedIdentity::fromPayload([
            'sub' => 'user-123',
            'email' => 'person@example.test',
            'username' => 'person',
            'email_verified' => true,
            'project_id' => 'project-123',
            'project_slug' => 'example',
            'project_mode' => 'sandbox',
            'client_id' => 'client-123',
            'session_id' => 'session-123',
            'roles' => ['admin'],
            'permissions' => ['reports.read'],
            'authorization_version' => 2,
            'is_temporary' => true,
            'temporary_expires_at' => '2026-08-11T12:00:00Z',
            'exp' => 1_786_444_800,
        ], 'sandbox');

        $this->assertSame('user-123', $identity->userId);
        $this->assertSame('sandbox', $identity->connection);
        $this->assertSame(['admin'], $identity->roles);
        $this->assertTrue($identity->can('reports.read'));
        $this->assertFalse($identity->can('reports.write'));
    }
}
