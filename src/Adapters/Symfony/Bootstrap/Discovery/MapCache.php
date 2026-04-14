<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Discovery;

/**
 * Lightweight map cache with directory/file mtime manifest.
 *
 * Used to avoid rescanning the filesystem on every boot in Symfony apps.
 */
final class MapCache
{
    private const MANIFEST_VERSION = 1;

    /**
     * @param  callable(array<int,string>): array<mixed>  $builder
     * @param  array<int,string>  $directories
     * @param  array<int,string>  $autoRefreshEnv
     * @return array<mixed>
     */
    public static function load(
        string $type,
        array $directories,
        callable $builder,
        string $cacheFile,
        string $manifestFile,
        bool $cacheEnabled,
        array $autoRefreshEnv,
        string $environment,
        bool $forceRebuild = false
    ): array {
        if (! $cacheEnabled || $forceRebuild || self::shouldRefresh($autoRefreshEnv, $environment)) {
            return self::rebuild($directories, $builder, $cacheFile, $manifestFile);
        }

        if (self::isFresh($directories, $cacheFile, $manifestFile)) {
            $cached = @include $cacheFile;

            return is_array($cached) ? $cached : self::rebuild($directories, $builder, $cacheFile, $manifestFile);
        }

        return self::rebuild($directories, $builder, $cacheFile, $manifestFile);
    }

    /**
     * @param  callable(array<int,string>): array<mixed>  $builder
     * @param  array<int,string>  $directories
     * @return array<mixed>
     */
    private static function rebuild(
        array $directories,
        callable $builder,
        string $cacheFile,
        string $manifestFile
    ): array {
        $usableDirs = self::filterExistingDirectories($directories);
        $map = $builder($usableDirs);
        self::writeCache($cacheFile, $map);
        self::writeManifest($usableDirs, $manifestFile);

        return $map;
    }

    /**
     * @param  array<mixed>  $map
     */
    private static function writeCache(string $cacheFile, array $map): void
    {
        self::ensureDirectory(dirname($cacheFile));
        $contents = "<?php\n\nreturn ".var_export($map, true).";\n";
        file_put_contents($cacheFile, $contents, LOCK_EX);
    }

    /**
     * @param  array<int,string>  $directories
     */
    private static function writeManifest(array $directories, string $manifestFile): void
    {
        self::ensureDirectory(dirname($manifestFile));

        $files = MapScanner::files($directories);
        $dirMtimes = [];
        foreach ($directories as $directory) {
            $dirMtimes[self::normalizePath($directory)] = self::safeFileMTime($directory);
        }

        $fileMtimes = [];
        foreach ($files as $file) {
            $fileMtimes[self::normalizePath($file)] = self::safeFileMTime($file);
        }

        $payload = [
            'version' => self::MANIFEST_VERSION,
            'directories' => $dirMtimes,
            'files' => $fileMtimes,
        ];

        $contents = "<?php\n\nreturn ".var_export($payload, true).";\n";
        file_put_contents($manifestFile, $contents, LOCK_EX);
    }

    /**
     * @param  array<int,string>  $directories
     */
    private static function isFresh(array $directories, string $cacheFile, string $manifestFile): bool
    {
        if (! is_file($cacheFile) || ! is_file($manifestFile)) {
            return false;
        }

        $manifest = @include $manifestFile;
        if (! is_array($manifest) || ($manifest['version'] ?? null) !== self::MANIFEST_VERSION) {
            return false;
        }

        $dirMtimes = (array) ($manifest['directories'] ?? []);
        foreach ($directories as $directory) {
            $normalized = self::normalizePath($directory);
            $expected = $dirMtimes[$normalized] ?? null;
            if ($expected === null || self::safeFileMTime($directory) !== (int) $expected) {
                return false;
            }
        }

        $fileMtimes = (array) ($manifest['files'] ?? []);
        foreach ($fileMtimes as $file => $mtime) {
            if (! is_file($file)) {
                return false;
            }
            if (self::safeFileMTime($file) !== (int) $mtime) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int,string>  $autoRefreshEnv
     */
    private static function shouldRefresh(array $autoRefreshEnv, string $environment): bool
    {
        return in_array($environment, $autoRefreshEnv, true);
    }

    private static function safeFileMTime(string $path): int
    {
        $mtime = @filemtime($path);

        return $mtime === false ? 0 : (int) $mtime;
    }

    private static function ensureDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param  array<int,string>  $directories
     * @return array<int,string>
     */
    private static function filterExistingDirectories(array $directories): array
    {
        $usable = [];
        foreach ($directories as $directory) {
            $directory = (string) $directory;
            if ($directory === '') {
                continue;
            }
            $real = realpath($directory) ?: $directory;
            if (is_dir($real)) {
                $usable[] = $real;
            }
        }

        return $usable === [] ? $directories : array_values(array_unique($usable));
    }
}
