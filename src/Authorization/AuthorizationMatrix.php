<?php

declare(strict_types=1);

namespace Zolta\Http\Authorization;

use Zolta\Http\Authorization\Interfaces\UserInterface;
use Zolta\Http\Authorization\Interfaces\UserResolverInterface;
use Zolta\Support\ContainerRegistry;

/**
 * Centralized ability/permission resolver powered by configuration.
 *
 * - abilities map to a list of permission strings
 * - authorized entries may contain abilities or raw permissions
 * - permissions are read from the current user using configured attribute paths
 */
final class AuthorizationMatrix
{
    /** @var array<string, list<string>> */
    private static array $abilities = [];

    /** @var list<string> */
    private static array $userAttributes = ['permissions', 'role.permissions', 'roles.*.permissions'];

    private static ?string $userClass = null;

    private static bool $configured = false;

    private static bool $abilitiesConfigured = false;

    private static ?UserResolverInterface $userResolver = null;

    /**
     * Set the user resolver implementation for framework-specific user resolution.
     */
    public static function setUserResolver(UserResolverInterface $userResolver): void
    {
        self::$userResolver = $userResolver;
    }

    /**
     * Configure the matrix.
     *
     * Expected shape:
     * [
     *   'abilities' => ['manage_users' => ['users.read', 'users.write']],
     *   'user' => [
     *     'class' => App\Models\User::class,
     *     'attributes' => ['permissions', 'role.permissions', 'roles.*.permissions'],
     *   ],
     * ]
     *
     * @param  array<string,mixed>  $config
     */
    public static function configure(array $config): void
    {
        $abilities = [];
        foreach (($config['abilities'] ?? []) as $ability => $permissions) {
            $ability = (string) $ability;
            $abilities[$ability] = array_values(array_filter(
                array_map(static fn($v): string => (string) $v, (array) $permissions),
                static fn(string $v): bool => $v !== ''
            ));
        }

        self::$abilities = $abilities;
        $configuredAttributes = (array) ($config['user']['permissions'] ?? $config['user']['attributes'] ?? []);
        self::$userAttributes = array_values(array_filter(
            array_map(static fn($v): string => (string) $v, $configuredAttributes),
            static fn(string $v): bool => $v !== ''
        )) ?: ['permissions', 'role.permissions', 'roles.*.permissions'];
        self::$userClass = isset($config['user']['class']) && (string) $config['user']['class'] !== ''
            ? (string) $config['user']['class']
            : null;
        self::$configured = true;
        self::$abilitiesConfigured = true;
    }

    /**
     * Override permission extraction paths.
     *
     * @param  list<string>  $attributes
     */
    public static function setUserAttributes(array $attributes): void
    {
        self::$userAttributes = array_values(array_filter(
            array_map(static fn(string $v): string => (string) $v, $attributes),
            static fn(string $v): bool => $v !== ''
        )) ?: self::$userAttributes;
    }

    /**
     * Configure abilities only (e.g. from Identity defaults).
     *
     * @param  array<string,list<string>>  $abilities
     */
    public static function setAbilities(array $abilities): void
    {
        $mapped = [];
        foreach ($abilities as $ability => $permissions) {
            $ability = (string) $ability;
            $mapped[$ability] = array_values(array_filter(
                array_map(static fn($v): string => (string) $v, (array) $permissions),
                static fn(string $v): bool => $v !== ''
            ));
        }

        self::$abilities = $mapped;
        self::$abilitiesConfigured = true;
        self::$configured = true;
    }

    public static function isConfigured(): bool
    {
        return self::$configured;
    }

    /**
     * Get the current user via the configured resolver.
     */
    public static function getCurrentUser(): ?UserInterface
    {
        return self::currentUser();
    }

    /**
     * Lazily configure from environment (Laravel config or Symfony parameters) if not already configured.
     */
    private static function ensureConfigured(): void
    {
        if (self::$configured) {
            return;
        }

        // Try to hydrate abilities from the configured identity class (if any).
        try {
            $container = ContainerRegistry::get();
            $paramName = 'zolta.identity.class';
            if ($container->has($paramName)) {
                $identityClass = (string) $container->get($paramName);
                if ($identityClass && class_exists($identityClass) && is_subclass_of($identityClass, Identity::class)) {
                    /** @var class-string<\Zolta\Core\Application\Security\Identity> $identityClass */
                    self::setAbilities($identityClass::abilities());
                }
            }
        } catch (\Throwable) {
            // ignore and remain with defaults unless set programmatically
        }

        // Configless by default; abilities may still be configured programmatically.
        self::$configured = true; // mark as attempted to avoid repeated work
    }

    /**
     * Evaluate whether the given ability/permission(s) are granted for the user.
     *
     * @param  string|list<string>  $abilitiesOrPermissions
     */
    public static function isGranted(string|array $abilitiesOrPermissions, ?UserInterface $user = null): bool
    {
        self::ensureConfigured();

        if (! $user instanceof UserInterface) {
            $user = self::currentUser();
        }

        $requiredPermissions = self::requiredPermissions($abilitiesOrPermissions);
        if ($requiredPermissions === []) {
            $normalized = is_array($abilitiesOrPermissions) ? $abilitiesOrPermissions : [$abilitiesOrPermissions];
            $allEmptyAbilities = true;
            foreach ($normalized as $entry) {
                $entry = (string) $entry;
                if ($entry === '') {
                    continue;
                }
                if (! isset(self::$abilities[$entry])) {
                    $allEmptyAbilities = false;
                    break;
                }
                if (self::$abilities[$entry] !== []) {
                    $allEmptyAbilities = false;
                    break;
                }
            }

            if ($allEmptyAbilities) {
                self::debugLog('AuthMatrix granted (empty abilities)', $abilitiesOrPermissions, [], $user);

                return true;
            }

            self::debugLog('AuthMatrix denied (no required permissions derived)', $abilitiesOrPermissions, [], $user);

            return false;
        }

        $resolvedUser = $user ?? self::currentUser();
        $userPermissions = self::permissionsForUser($resolvedUser);
        if ($userPermissions === []) {
            self::debugLog('AuthMatrix denied (no user permissions)', $abilitiesOrPermissions, $requiredPermissions, $user);

            return false;
        }

        $lookup = array_fill_keys($userPermissions, true);
        foreach ($requiredPermissions as $requiredPermission) {
            if (! isset($lookup[$requiredPermission])) {
                self::debugLog('AuthMatrix denied (missing permission)', $abilitiesOrPermissions, $requiredPermissions, $user, $userPermissions);

                return false;
            }
        }

        self::debugLog('AuthMatrix granted', $abilitiesOrPermissions, $requiredPermissions, $user, $userPermissions);

        return true;
    }

    /**
     * Expand abilities into concrete permissions.
     *
     * @param  string|list<string>  $abilitiesOrPermissions
     * @return list<string>
     */
    public static function requiredPermissions(string|array $abilitiesOrPermissions): array
    {
        self::ensureConfigured();

        $normalized = is_array($abilitiesOrPermissions) ? $abilitiesOrPermissions : [$abilitiesOrPermissions];
        $resolved = [];

        foreach ($normalized as $entry) {
            $entry = (string) $entry;
            if ($entry === '') {
                continue;
            }

            if (isset(self::$abilities[$entry])) {
                foreach (self::$abilities[$entry] as $perm) {
                    $resolved[] = $perm;
                }
            } else {
                $resolved[] = $entry;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Collect permissions from the given user using configured attributes.
     *
     * @return list<string>
     */
    public static function permissionsForUser(?object $user): array
    {
        if (! is_object($user)) {
            return [];
        }

        // If the user is wrapped in an adapter, unwrap to the framework model
        // so the class check and path extraction run against the real object.
        $source = ($user instanceof UserAdapter)
            ? $user->getFrameworkUser()
            : $user;

        if (self::$userClass !== null && ! $source instanceof self::$userClass) {
            return [];
        }

        // Framework-specific eager loading should be handled by the UserResolver implementation
        // in the Infrastructure layer (e.g., Doctrine lazy loading in Laravel/Symfony adapters)

        $collected = [];
        foreach (self::$userAttributes as $userAttribute) {
            $values = self::extractByPath($source, $userAttribute);
            foreach ($values as $value) {
                // Normalize to a string permission name if possible.
                $str = null;

                if (is_scalar($value)) {
                    $str = (string) $value;
                } elseif (is_object($value)) {
                    if (method_exists($value, 'getName')) {
                        $name = $value->getName();
                        if (is_scalar($name)) {
                            $str = (string) $name;
                        } elseif (is_object($name) && method_exists($name, '__toString')) {
                            $str = (string) $name;
                        } elseif (is_object($name) && method_exists($name, 'getValue') && is_scalar($name->getValue())) {
                            $str = (string) $name->getValue();
                        }
                    } elseif (method_exists($value, 'getValue') && is_scalar($value->getValue())) {
                        $str = (string) $value->getValue();
                    } elseif (method_exists($value, '__toString')) {
                        $str = (string) $value;
                    }
                }

                if ($str !== null && $str !== '') {
                    $collected[] = $str;
                }
            }
        }

        return array_values(array_unique($collected));
    }

    /**
     * Lightweight debug logger (writes to PHP error log).
     *
     * @param  list<string>  $required
     * @param  list<string>|null  $userPermissions
     */
    /**
     * @param  string|array<int|string,string>  $abilitiesOrPermissions
     * @param  array<int|string,string>  $required
     * @param  array<int|string,string>|null  $userPermissions
     */
    private static function debugLog(
        string $message,
        string|array $abilitiesOrPermissions,
        array $required,
        ?object $user,
        ?array $userPermissions = null
    ): void {
        // no-op; debug logging removed for clean runtime
    }

    /**
     * Extract values from a user using dot-paths with optional wildcards (*).
     *
     * @param  object|array<int|string,mixed>  $source
     * @return list<string>
     */
    private static function extractByPath(object|array $source, string $path): array
    {
        $segments = array_values(array_filter(explode('.', $path), static fn(string $v): bool => $v !== ''));

        $current = [$source];
        foreach ($segments as $segment) {
            $next = [];
            $wildcard = $segment === '*';

            foreach ($current as $item) {
                if ($wildcard) {
                    if (is_iterable($item)) {
                        foreach ($item as $value) {
                            $next[] = $value;
                        }
                    }

                    continue;
                }

                $value = null;
                if (is_array($item) && array_key_exists($segment, $item)) {
                    $value = $item[$segment];
                } elseif (is_object($item) && isset($item->{$segment})) {
                    $value = $item->{$segment};
                } elseif (is_object($item)) {
                    $accessor = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $segment)));
                    if (method_exists($item, $accessor)) {
                        $value = $item->{$accessor}();
                    } elseif (method_exists($item, $segment)) {
                        $value = $item->{$segment}();
                    }
                }

                if ($value !== null) {
                    $next[] = $value;
                }
            }

            $current = $next;
        }

        $flattened = [];

        $addValue = static function ($value) use (&$flattened, &$addValue): void {
            if (is_iterable($value)) {
                foreach ($value as $v) {
                    $addValue($v);
                }

                return;
            }

            if (is_scalar($value)) {
                $flattened[] = (string) $value;

                return;
            } elseif (is_object($value)) {
                // last-resort: try 'getName' or 'getValue' as common accessors
                if (method_exists($value, 'getName')) {
                    $flattened[] = (string) $value->getName();
                } elseif (method_exists($value, 'getValue')) {
                    $flattened[] = (string) $value->getValue();
                } elseif (method_exists($value, '__toString')) {
                    $flattened[] = (string) $value;
                }
            }
        };

        foreach ($current as $value) {
            $addValue($value);
        }

        return $flattened;
    }

    /**
     * Get the current user using the configured UserResolver.
     */
    private static function currentUser(): ?UserInterface
    {
        if (! self::$userResolver instanceof UserResolverInterface) {
            return null;
        }

        try {
            return self::$userResolver->currentUser();
        } catch (\Throwable) {
            // If resolver fails, return null to avoid breaking authorization checks
            return null;
        }
    }
}
