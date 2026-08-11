<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization;

use Zolta\Http\Authorization\Interfaces\UserIdentityInterface;

/**
 * Code-first user identity implementation with sensible defaults.
 */
class Identity implements UserIdentityInterface
{
    /** @var array<string,list<string>> */
    protected static array $abilities = [];

    /** @var list<string> */
    protected static array $permissionPaths = [];

    /** @var array<string|int,mixed> */
    protected static array $schemaDefinition = [];

    /**
     * Persistence model used to eager-load relations.
     */
    protected static string $entity = '';

    /**
     * Domain class we want to project to (optional).
     */
    protected static ?string $domainClass = null;

    /**
     * @param  array<int,array{id:string|null,name:string,permissions?:array<int,array{id:string|null,name:string}>}>  $roleEntries
     * @param  string[]  $permissions
     */
    public function __construct(
        private readonly string|int $id,
        private readonly array $roleEntries,
        private readonly array $permissions,
        private readonly ?object $domainUser = null
    ) {}

    public function getId(): string|int
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $role): string => $role['name'],
            $this->roleEntries
        )));
    }

    /**
     * @return array<int,array{id:string|null,name:string,permissions?:array<int,array{id:string|null,name:string}>}>
     */
    public function getRoleEntries(): array
    {
        return $this->roleEntries;
    }

    /**
     * @return string[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getDomainUser(): ?object
    {
        return $this->domainUser;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * @param  string[]  $abilities
     */
    public function isAuthorized(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->can($ability)) {
                return true;
            }
        }

        return false;
    }

    public static function fromUser(?object $user, ?string $identityClass = null): ?self
    {
        if (! is_object($user)) {
            return null;
        }

        // Prefer explicit identity class, fallback to app config if provided.
        if (! $identityClass && function_exists('config')) {
            $identityClass = config('zolta.identity.class') ?? config('zolta_identity.class');
        }

        $targetClass = $identityClass ? (string) $identityClass : self::class;
        if (! is_subclass_of($targetClass, self::class) && $targetClass !== self::class) {
            throw new \RuntimeException("Configured identity class {$targetClass} must extend ".self::class);
        }

        // Always refresh abilities from the identity defaults to keep mappings in sync.
        AuthorizationMatrix::setAbilities($targetClass::abilities());

        // Apply identity-specific config (permission paths, schema) if provided.
        $config = $targetClass::config();
        if (isset($config['permissions']) && is_array($config['permissions'])) {
            AuthorizationMatrix::setUserAttributes($config['permissions']);
        }

        $domainUser = $targetClass::restoreDomainUser($user);
        $permissionSource = $user;

        $expectedUserClass = $targetClass::userClass();
        if ($expectedUserClass && ! $domainUser instanceof $expectedUserClass) {
            // If the framework user is of the expected type, keep it as the domain user.
            if ($user instanceof $expectedUserClass) {
                $domainUser = $user;
            } else {
                throw new \RuntimeException("Identity expects user of type {$expectedUserClass}, got ".get_debug_type($domainUser));
            }
        }

        $id = self::extractId($permissionSource);
        $roles = self::extractRoles($permissionSource);
        $permissions = AuthorizationMatrix::permissionsForUser($permissionSource);

        return new $targetClass($id, $roles, $permissions, $domainUser);
    }

    /**
     * Convenience accessor for the current identity (delegates to UserIdentity::current()).
     */
    public static function current(): ?UserIdentityInterface
    {
        return UserIdentity::current();
    }

    /**
     * Default abilities mapping; override in subclasses.
     *
     * @return array<string,list<string>>
     */
    public static function abilities(): array
    {
        return static::$abilities;
    }

    /**
     * Identity-specific config for permission paths/schema.
     *
     * @return array{permissions?:list<string>,schema?:array<string,mixed>}
     */
    public static function config(): array
    {
        return [
            'permissions' => static::$permissionPaths,
            'schema' => static::$schemaDefinition,
        ];
    }

    protected static function userClass(): ?string
    {
        if (static::$domainClass) {
            return static::$domainClass;
        }

        if (static::$entity !== '') {
            return static::$entity;
        }

        // Provide a sensible default for Laravel apps via config if available.
        if (function_exists('config')) {
            /** @var mixed $model */
            $model = config('auth.providers.users.model');
            if (is_string($model) && $model !== '') {
                return $model;
            }
        }

        return null;
    }

    private static function normalizeScalarId(mixed $id): ?string
    {
        if (is_object($id)) {
            if (method_exists($id, 'getValue') && is_scalar($id->getValue())) {
                return (string) $id->getValue();
            }
            if (method_exists($id, '__toString')) {
                return (string) $id;
            }

            return null;
        }

        return is_scalar($id) ? (string) $id : null;
    }

    private static function extractId(object $user): string|int
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            $authId = $user->getAuthIdentifier();
            if (is_scalar($authId)) {
                return $authId;
            }
        }
        if (method_exists($user, 'getId')) {
            $id = $user->getId();
            if (is_scalar($id)) {
                return $id;
            }
        }

        if (method_exists($user, 'getUserIdentifier')) {
            return (string) $user->getUserIdentifier();
        }

        if (isset($user->id)) {
            /** @var mixed $id */
            $id = $user->id;
            if (is_scalar($id)) {
                return $id;
            }
        }

        return '';
    }

    /**
     * @return array<int,array{id:string|null,name:string,permissions?:array<int,array{id:string|null,name:string}>}>
     */
    private static function extractRoles(object $user): array
    {
        $roles = [];
        $sourceRoles = null;

        if (isset($user->roles)) {
            $sourceRoles = $user->roles;
        } elseif (isset($user->role)) {
            $sourceRoles = [$user->role];
        }

        if (method_exists($user, 'getRoles')) {
            $roles = $user->getRoles();
        } elseif ($sourceRoles !== null) {
            $roles = $sourceRoles;
        }

        // If getRoles() only returns scalar role names but we have object relations,
        // prefer the richer role relation collection for metadata/permissions.
        $roles = $roles instanceof \Traversable ? iterator_to_array($roles) : (is_array($roles) ? $roles : []);
        $allScalars = $roles !== [] && array_reduce($roles, static fn (bool $carry, $item): bool => $carry && (is_scalar($item) || $item === null), true);
        if ($allScalars && $sourceRoles !== null) {
            $roles = $sourceRoles instanceof \Traversable ? iterator_to_array($sourceRoles) : (is_array($sourceRoles) ? $sourceRoles : $roles);
        }

        $normalized = array_map(
            static function ($role): ?array {
                $id = null;
                $perms = [];
                if (is_object($role)) {
                    $id = $role->id ?? (method_exists($role, 'getId') ? $role->getId() : null);
                    $id = self::normalizeScalarId($id);

                    // Resolve role name safely without forcing string cast on domain entities.
                    $name = null;
                    if (isset($role->name) && (is_scalar($role->name) || (is_object($role->name) && method_exists($role->name, '__toString')))) {
                        $name = (string) $role->name;
                    } elseif (isset($role->role) && (is_scalar($role->role) || (is_object($role->role) && method_exists($role->role, '__toString')))) {
                        $name = (string) $role->role;
                    } elseif (method_exists($role, 'getName')) {
                        $rname = $role->getName();
                        if (is_scalar($rname)) {
                            $name = (string) $rname;
                        } elseif (is_object($rname)) {
                            if (method_exists($rname, 'getValue') && is_scalar($rname->getValue())) {
                                $name = (string) $rname->getValue();
                            } elseif (method_exists($rname, '__toString')) {
                                $name = (string) $rname;
                            }
                        }
                    } elseif (method_exists($role, '__toString')) {
                        $name = (string) $role;
                    }

                    if (isset($role->permissions) && is_iterable($role->permissions)) {
                        foreach ($role->permissions as $perm) {
                            $pid = null;
                            $pname = null;
                            if (is_object($perm)) {
                                $pid = $perm->id ?? (method_exists($perm, 'getId') ? $perm->getId() : null);
                                $pid = self::normalizeScalarId($pid);
                                if (isset($perm->name) && (is_scalar($perm->name) || (is_object($perm->name) && method_exists($perm->name, '__toString')))) {
                                    $pname = (string) $perm->name;
                                } elseif (method_exists($perm, 'getName')) {
                                    $nm = $perm->getName();
                                    if (is_scalar($nm)) {
                                        $pname = (string) $nm;
                                    } elseif (is_object($nm) && method_exists($nm, 'getValue') && is_scalar($nm->getValue())) {
                                        $pname = (string) $nm->getValue();
                                    } elseif (is_object($nm) && method_exists($nm, '__toString')) {
                                        $pname = (string) $nm;
                                    }
                                } elseif (method_exists($perm, '__toString')) {
                                    $pname = (string) $perm;
                                }
                            } elseif (is_scalar($perm)) {
                                $pname = (string) $perm;
                            }

                            if ($pname !== null) {
                                $pname = strtolower((string) $pname);
                            }
                            if ($pname === null || $pname === '') {
                                continue;
                            }
                            $perms[$pid ?? $pname] = ['id' => $pid, 'name' => $pname];
                        }
                        $perms = array_values($perms);
                    }
                } else {
                    $name = (string) $role;
                }

                $name = preg_replace('/^role_/i', '', $name ?? '');
                $name = str_starts_with($name, 'ROLE_') ? substr($name, 5) : $name;
                $name = strtolower((string) $name);

                return $name === '' ? null : ['id' => $id, 'name' => $name, 'permissions' => $perms];
            },
            $roles
        );

        $normalized = array_values(array_filter($normalized));
        $unique = [];
        foreach ($normalized as $entry) {
            $key = $entry['id'] ?? $entry['name'];
            $unique[$key] = $entry;
        }

        return array_values($unique);
    }

    /**
     * Hook for host apps to project the framework user into a domain aggregate.
     */
    protected static function restoreDomainUser(object $frameworkUser): object
    {
        return $frameworkUser;
    }
}
