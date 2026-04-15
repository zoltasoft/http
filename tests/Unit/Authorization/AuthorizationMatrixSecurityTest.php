<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\UserInterface;

final class AuthorizationMatrixSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset static state between tests via configure
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions']],
        ]);
    }

    // ── Permission extraction path traversal ────────────────────────────

    public function test_wildcard_path_extracts_nested_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage' => ['users.read', 'users.write']],
            'user' => [
                'attributes' => ['roles.*.permissions'],
            ],
        ]);

        $user = $this->createUserWithNestedPermissions([
            ['name' => 'admin', 'permissions' => ['users.read', 'users.write']],
        ]);

        $this->assertTrue(AuthorizationMatrix::isGranted('manage', $user));
    }

    public function test_denies_when_user_missing_required_permission(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage' => ['users.read', 'users.write']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['users.read']); // missing users.write

        $this->assertFalse(AuthorizationMatrix::isGranted('manage', $user));
    }

    public function test_denies_when_user_has_no_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage' => ['users.read']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions([]);

        $this->assertFalse(AuthorizationMatrix::isGranted('manage', $user));
    }

    public function test_grants_empty_ability_that_maps_to_no_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['view_public' => []],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions([]);

        $this->assertTrue(AuthorizationMatrix::isGranted('view_public', $user));
    }

    public function test_raw_permission_treated_as_literal_when_not_in_abilities(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['direct.permission']);

        $this->assertTrue(AuthorizationMatrix::isGranted('direct.permission', $user));
    }

    public function test_denies_null_user(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage' => ['users.read']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $this->assertFalse(AuthorizationMatrix::isGranted('manage', null));
    }

    public function test_multiple_abilities_require_all_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [
                'read' => ['data.read'],
                'write' => ['data.write'],
            ],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['data.read']); // missing data.write

        $this->assertFalse(AuthorizationMatrix::isGranted(['read', 'write'], $user));
    }

    public function test_multiple_abilities_granted_when_all_permissions_present(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [
                'read' => ['data.read'],
                'write' => ['data.write'],
            ],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['data.read', 'data.write']);

        $this->assertTrue(AuthorizationMatrix::isGranted(['read', 'write'], $user));
    }

    // ── Required permissions expansion ──────────────────────────────────

    public function test_required_permissions_expands_abilities(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['admin' => ['perm.a', 'perm.b']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $perms = AuthorizationMatrix::requiredPermissions('admin');

        $this->assertContains('perm.a', $perms);
        $this->assertContains('perm.b', $perms);
    }

    public function test_required_permissions_passes_through_raw_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions']],
        ]);

        $perms = AuthorizationMatrix::requiredPermissions('raw.perm');

        $this->assertSame(['raw.perm'], $perms);
    }

    public function test_required_permissions_deduplicates(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [
                'a' => ['perm.x'],
                'b' => ['perm.x'],
            ],
            'user' => ['attributes' => ['permissions']],
        ]);

        $perms = AuthorizationMatrix::requiredPermissions(['a', 'b']);

        $this->assertCount(1, $perms);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function createUserWithPermissions(array $permissions): UserInterface
    {
        return new class($permissions) implements UserInterface
        {
            public function __construct(public readonly array $permissions) {}

            public function getId(): string|int
            {
                return 1;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }

    private function createUserWithNestedPermissions(array $roles): UserInterface
    {
        $roleObjs = array_map(function (array $role) {
            return new class($role['name'], $role['permissions'])
            {
                public function __construct(
                    public readonly string $name,
                    public readonly array $permissions,
                ) {}
            };
        }, $roles);

        return new class($roleObjs) implements UserInterface
        {
            public function __construct(public readonly array $roles) {}

            public function getId(): string|int
            {
                return 1;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };
    }
}
