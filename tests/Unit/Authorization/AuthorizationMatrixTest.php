<?php

declare(strict_types=1);

namespace Zolta\Tests\Unit\Authorization;

use PHPUnit\Framework\TestCase;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\UserInterface;

final class AuthorizationMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static state between tests
        $ref = new \ReflectionClass(AuthorizationMatrix::class);
        foreach (['abilities', 'userAttributes', 'userClass', 'configured', 'abilitiesConfigured', 'userResolver'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, match ($prop) {
                'abilities' => [],
                'userAttributes' => ['permissions', 'role.permissions', 'roles.*.permissions'],
                'userClass' => null,
                'configured' => false,
                'abilitiesConfigured' => false,
                'userResolver' => null,
            });
        }
    }

    // ── Configuration ─────────────────────────────────────────────────────

    public function test_configure_sets_abilities_and_user_attributes(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [
                'manage_users' => ['users.read', 'users.write', 'users.delete'],
                'view_reports' => ['reports.read'],
            ],
            'user' => [
                'class' => null,
                'attributes' => ['permissions'],
            ],
        ]);

        $this->assertTrue(AuthorizationMatrix::isConfigured());
    }

    public function test_set_abilities_separately(): void
    {
        AuthorizationMatrix::setAbilities([
            'admin' => ['users.read', 'users.write'],
        ]);

        $this->assertTrue(AuthorizationMatrix::isConfigured());
        $this->assertSame(['users.read', 'users.write'], AuthorizationMatrix::requiredPermissions('admin'));
    }

    // ── Required permissions resolution ───────────────────────────────────

    public function test_ability_expands_to_permissions(): void
    {
        AuthorizationMatrix::setAbilities([
            'manage_users' => ['users.read', 'users.write'],
        ]);

        $result = AuthorizationMatrix::requiredPermissions('manage_users');

        $this->assertSame(['users.read', 'users.write'], $result);
    }

    public function test_raw_permission_passes_through(): void
    {
        AuthorizationMatrix::setAbilities([]);

        $result = AuthorizationMatrix::requiredPermissions('orders.create');

        $this->assertSame(['orders.create'], $result);
    }

    public function test_multiple_abilities_expanded(): void
    {
        AuthorizationMatrix::setAbilities([
            'read_users' => ['users.read'],
            'write_users' => ['users.write'],
        ]);

        $result = AuthorizationMatrix::requiredPermissions(['read_users', 'write_users']);

        $this->assertSame(['users.read', 'users.write'], $result);
    }

    public function test_duplicate_permissions_deduplicated(): void
    {
        AuthorizationMatrix::setAbilities([
            'a' => ['users.read', 'users.write'],
            'b' => ['users.read', 'orders.read'],
        ]);

        $result = AuthorizationMatrix::requiredPermissions(['a', 'b']);

        $this->assertCount(3, $result);
        $this->assertContains('users.read', $result);
        $this->assertContains('users.write', $result);
        $this->assertContains('orders.read', $result);
    }

    public function test_empty_string_filtered_out(): void
    {
        $result = AuthorizationMatrix::requiredPermissions(['', 'users.read', '']);

        $this->assertSame(['users.read'], $result);
    }

    // ── Permission grant checks ───────────────────────────────────────────

    public function test_grants_when_user_has_all_required_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage_users' => ['users.read', 'users.write']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['users.read', 'users.write', 'orders.read']);

        $this->assertTrue(AuthorizationMatrix::isGranted('manage_users', $user));
    }

    public function test_denies_when_user_missing_permission(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['manage_users' => ['users.read', 'users.write']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['users.read']); // missing users.write

        $this->assertFalse(AuthorizationMatrix::isGranted('manage_users', $user));
    }

    public function test_denies_when_user_has_no_permissions(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['admin' => ['admin.access']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions([]);

        $this->assertFalse(AuthorizationMatrix::isGranted('admin', $user));
    }

    public function test_grants_raw_permission_check(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['invoices.view']);

        $this->assertTrue(AuthorizationMatrix::isGranted('invoices.view', $user));
    }

    public function test_denies_when_null_user(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => ['admin' => ['admin.access']],
            'user' => ['attributes' => ['permissions']],
        ]);

        $this->assertFalse(AuthorizationMatrix::isGranted('admin', null));
    }

    public function test_grants_empty_ability_with_no_permissions(): void
    {
        AuthorizationMatrix::setAbilities([
            'public' => [],
        ]);

        $user = $this->createUserWithPermissions([]);

        // Empty ability means no permissions required → always granted
        $this->assertTrue(AuthorizationMatrix::isGranted('public', $user));
    }

    // ── permissions for user extraction ───────────────────────────────────

    public function test_extracts_permissions_from_direct_property(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions']],
        ]);

        $user = $this->createUserWithPermissions(['a', 'b']);

        $result = AuthorizationMatrix::permissionsForUser($user);

        $this->assertSame(['a', 'b'], $result);
    }

    public function test_extracts_permissions_from_nested_path(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['role.permissions']],
        ]);

        $user = new class implements UserInterface
        {
            public object $role;

            public function __construct()
            {
                $this->role = new class
                {
                    /** @var list<string> */
                    public array $permissions = ['x.read', 'x.write'];
                };
            }

            public function getId(): string|int
            {
                return 1;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };

        $result = AuthorizationMatrix::permissionsForUser($user);

        $this->assertContains('x.read', $result);
        $this->assertContains('x.write', $result);
    }

    public function test_extracts_permissions_with_wildcard_path(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['roles.*.permissions']],
        ]);

        $role1 = new class
        {
            /** @var list<string> */
            public array $permissions = ['posts.read'];
        };
        $role2 = new class
        {
            /** @var list<string> */
            public array $permissions = ['posts.write', 'posts.delete'];
        };

        $user = new class($role1, $role2) implements UserInterface
        {
            /** @var list<object> */
            public array $roles;

            public function __construct(object $r1, object $r2)
            {
                $this->roles = [$r1, $r2];
            }

            public function getId(): string|int
            {
                return 2;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };

        $result = AuthorizationMatrix::permissionsForUser($user);

        $this->assertCount(3, $result);
        $this->assertContains('posts.read', $result);
        $this->assertContains('posts.write', $result);
        $this->assertContains('posts.delete', $result);
    }

    public function test_returns_empty_for_null_user(): void
    {
        $this->assertSame([], AuthorizationMatrix::permissionsForUser(null));
    }

    public function test_user_class_enforcement(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => [
                'class' => 'NonExistentUserClass',
                'attributes' => ['permissions'],
            ],
        ]);

        $user = $this->createUserWithPermissions(['a']);

        // User doesn't match the class constraint
        $this->assertSame([], AuthorizationMatrix::permissionsForUser($user));
    }

    // ── Permission deduplication ──────────────────────────────────────────

    public function test_duplicate_user_permissions_deduplicated(): void
    {
        AuthorizationMatrix::configure([
            'abilities' => [],
            'user' => ['attributes' => ['permissions', 'extraPermissions']],
        ]);

        $user = new class implements UserInterface
        {
            /** @var list<string> */
            public array $permissions = ['a', 'b'];

            /** @var list<string> */
            public array $extraPermissions = ['b', 'c'];

            public function getId(): string|int
            {
                return 1;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }
        };

        $result = AuthorizationMatrix::permissionsForUser($user);

        $this->assertCount(3, $result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $permissions
     */
    private function createUserWithPermissions(array $permissions): UserInterface
    {
        return new class($permissions) implements UserInterface
        {
            /** @param list<string> $permissions */
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
}
