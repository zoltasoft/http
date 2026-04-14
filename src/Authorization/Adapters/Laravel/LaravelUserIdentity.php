<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Identity;
use Zolta\Http\Authorization\Interfaces\UserIdentityInterface;

final readonly class LaravelUserIdentity implements UserIdentityInterface
{
    public function __construct(private Authenticatable $authenticatable) {}

    public function getId(): string|int
    {
        return $this->authenticatable->getAuthIdentifier();
    }

    public function getRoles(): array
    {
        return collect($this->getRoleEntries())
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    public function getRoleEntries(): array
    {
        $roles = [];
        if (method_exists($this->authenticatable, 'getRoles')) {
            $roles = $this->authenticatable->getRoles();
        } elseif (property_exists($this->authenticatable, 'roles') && $this->authenticatable->roles !== null) {
            $roles = $this->authenticatable->roles;
        } elseif (property_exists($this->authenticatable, 'role') && $this->authenticatable->role !== null) { // handle singular relation
            $roles = [$this->authenticatable->role];
        }

        $roleItems = [];
        if ($roles instanceof Collection) {
            $roleItems = $roles->all();
        } elseif (is_iterable($roles)) {
            $roleItems = is_array($roles) ? $roles : iterator_to_array($roles);
        }
        /** @var array<int,mixed> $roleItems */
        $roleItems = array_values($roleItems);
        /** @var Collection<int,mixed> $collection */
        $collection = collect($roleItems);
        $normalized = $collection
            ->map(function ($role): ?array {
                $id = null;
                $perms = [];
                if (is_object($role)) {
                    $id = $role->id ?? (method_exists($role, 'getId') ? $role->getId() : null);
                    $name = $role->name ?? $role->role ?? (string) $role;

                    if (isset($role->permissions) && is_iterable($role->permissions)) {
                        /** @var iterable<int,mixed> $permissions */
                        $permissions = $role->permissions;
                        /** @var Collection<int,mixed> $permCollection */
                        $permCollection = collect($permissions);
                        $perms = $permCollection
                            ->map(function ($perm): ?array {
                                $pid = null;
                                if (is_object($perm)) {
                                    $pid = $perm->id ?? (method_exists($perm, 'getId') ? $perm->getId() : null);
                                    $pname = $perm->name ?? (string) $perm;
                                } else {
                                    $pname = (string) $perm;
                                }

                                $pname = strtolower((string) $pname);

                                return $pname === '' ? null : ['id' => $pid, 'name' => $pname];
                            })
                            ->filter()
                            ->unique(fn (array $p) => $p['id'] ?? $p['name'])
                            ->values()
                            ->all();
                    }
                } else {
                    $name = (string) $role;
                }

                $name = str_starts_with($name, 'ROLE_') ? substr($name, 5) : $name;
                $name = strtolower($name);

                return $name === '' ? null : ['id' => $id, 'name' => $name, 'permissions' => $perms];
            })
            ->filter();

        return $normalized
            ->unique(fn (array $r) => $r['id'] ?? $r['name'])
            ->values()
            ->all();
    }

    public function getPermissions(): array
    {
        return AuthorizationMatrix::permissionsForUser($this->authenticatable);
    }

    public function can(string $permission): bool
    {
        if (class_exists(Gate::class)) {
            return Gate::forUser($this->authenticatable)->check($permission);
        }

        if (function_exists('auth')) {
            try {
                $guard = auth();
                if ($guard instanceof Factory) {
                    $guard = $guard->guard();
                }
                if (is_object($guard) && method_exists($guard, 'user')) {
                    $u = $guard->user();
                    if ($u && method_exists($u, 'can')) {
                        return (bool) $u->can($permission);
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return false;
    }

    public function isAuthorized(array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->can($ability)) {
                return true;
            }
        }

        return false;
    }

    public static function fromAuth(): ?Identity
    {
        if (! function_exists('auth')) {
            return null;
        }

        $guards = [];
        if (function_exists('config')) {
            $defaultGuard = (string) (config('auth.defaults.guard') ?? '');
            if ($defaultGuard !== '') {
                $guards[] = $defaultGuard;
            }
            $guards = array_merge($guards, array_keys((array) config('auth.guards', [])));
        }
        if ($guards === []) {
            $guards[] = null; // fallback to default guard
        }

        foreach ($guards as $guardName) {
            $guard = $guardName ? auth()->guard($guardName) : auth();
            if (! $guard || ! method_exists($guard, 'user')) {
                continue;
            }
            $user = $guard->user();
            if (! is_object($user)) {
                continue;
            }

            $identityClass = function_exists('config')
                ? (config('zolta_identity.class') ?? config('zolta.identity.class') ?? null)
                : null;
            $permissionPaths = [];
            if (is_string($identityClass) && is_subclass_of($identityClass, Identity::class)) {
                /** @var class-string<Identity> $identityClass */
                $cfg = $identityClass::config();
                $permissionPaths = isset($cfg['permissions']) && is_array($cfg['permissions']) ? $cfg['permissions'] : [];
            }

            // Eager load configured permission relations if supported.
            if ($permissionPaths !== [] && method_exists($user, 'loadMissing')) {
                $toLoad = [];
                foreach ($permissionPaths as $permissionPath) {
                    $firstSegment = explode('.', $permissionPath, 2)[0];
                    if ($firstSegment !== '') {
                        $toLoad[] = $firstSegment;
                    }
                }
                if ($toLoad !== []) {
                    $user->loadMissing(array_values(array_unique($toLoad)));
                }
            }

            if (! $identityClass) {
                throw new \RuntimeException('Identity class is not configured (zolta_identity.class).');
            }

            return Identity::fromUser($user, (string) $identityClass);
        }

        return null;
    }
}
