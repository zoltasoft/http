<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Zolta\Http\Router\Cache\ReflectionCache;

/**
 * Builds and caches attribute-discovered routes into bootstrap/cache/attribute_routes.php.
 * A companion manifest keeps track of file + directory mtimes and per-file routes
 * so we only rebuild what actually changed.
 */
final class AttributeRouteCache
{
    private const MANIFEST_VERSION = 2;

    private readonly string $servicesPath;

    private readonly string $cacheFile;

    private readonly string $manifestFile;

    /** @var array<string,int> */
    private array $files = [];

    /** @var array<string,int> */
    private array $directories = [];

    /** @var array<string,array<int,string>> */
    private array $routesByFile = [];

    /**
     * @param  string|null  $servicesPath  Override root path for discovery (defaults to app/Services)
     */
    public function __construct(?string $servicesPath = null)
    {
        $this->servicesPath = $servicesPath ?? app_path('Services');
        $this->cacheFile = base_path('bootstrap/cache/attribute_routes.php');
        $this->manifestFile = base_path('bootstrap/cache/attribute_routes_manifest.php');
    }

    /**
     * Fast token-based check whether a file declares at least one class/interface/trait.
     * This avoids attempting to autoload route files, plain php files, migrations whose filename differs from the class, etc.
     */
    private function fileContainsClassLike(string $file): bool
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }

        $tokens = token_get_all($content);
        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Explicitly rebuild attribute routes.
     *
     * @return array{routes:int,files:int}
     */
    public function build(bool $requireAfterBuild = true): array
    {
        $roots = $this->discoveryRoots();
        if ($roots === []) {
            if ($this->verboseLogging()) {
                Log::warning('⚠️ AttributeRouteCache rebuild skipped – no discovery roots resolved.');
            }

            return ['routes' => 0, 'files' => 0];
        }

        $this->files = [];
        $this->directories = [];
        $this->routesByFile = [];

        $startedAt = microtime(true);

        foreach ($roots as $root) {
            AttributeRouteLoader::load($root, function (string $path): void {
                $this->recordFile($path);
            }, function (string $fqcn): void {
                try {
                    ReflectionCache::clear($fqcn);
                } catch (\Throwable $e) {
                    Log::warning("⚠️ Unable to clear reflection cache for {$fqcn}: {$e->getMessage()}");
                }
            }, function (string $path, array $routesForFile): void {
                $relative = $this->toRelativePath($path);
                $this->routesByFile[$relative] = $routesForFile;
            });

            $this->recordDirectory($root);
        }

        $allRoutes = $this->flattenRoutesByFile();
        $compiled = $this->compileRouteFile($allRoutes);
        $this->writeFile($this->cacheFile, $compiled);
        $this->writeManifest($roots);

        if ($requireAfterBuild) {
            $this->requireCache();
            // Force Laravel to reload routes in development
            if (app()->environment(['local', 'development', 'testing'])) {
                $this->clearLaravelRouteCache();
            }
            $duration = number_format((microtime(true) - $startedAt) * 1000, 1);
            Log::info('✅ Cached '.count($allRoutes)." attribute routes in {$duration} ms.");
        }

        return [
            'routes' => count($allRoutes),
            'files' => count($this->files),
        ];
    }

    /**
     * Build routes for a single file and merge into existing cache.
     *
     * @return array{routes:int,files?:int,total_routes?:int}
     */
    public function buildSingleFile(string $filePath, bool $requireAfterBuild = true): array
    {
        // Validate file exists and is readable
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            if ($this->verboseLogging()) {
                Log::warning("⚠️ Single file build skipped – file not accessible: {$filePath}");
            }

            return ['routes' => 0];
        }

        if (! $this->fileContainsClassLike($filePath)) {
            if ($this->verboseLogging()) {
                Log::debug("⚠️ Single file build skipped – no class found in: {$filePath}");
            }

            return ['routes' => 0];
        }

        $manifest = $this->readManifest();
        if ($manifest === null || ! isset($manifest['routes_by_file'])) {
            if ($this->verboseLogging()) {
                Log::info('🔄 Single file change detected but manifest missing/outdated – triggering full rebuild');
            }

            return $this->build($requireAfterBuild);
        }

        $this->files = (array) ($manifest['files'] ?? []);
        $this->directories = (array) ($manifest['directories'] ?? []);
        $this->routesByFile = (array) ($manifest['routes_by_file'] ?? []);
        $roots = (array) ($manifest['roots'] ?? []);

        if ($roots === []) {
            return $this->build($requireAfterBuild);
        }

        $startedAt = microtime(true);
        $relativePath = $this->toRelativePath($filePath);

        $routesForFile = AttributeRouteLoader::loadFile($filePath, function (string $fqcn): void {
            try {
                ReflectionCache::clear($fqcn);
            } catch (\Throwable $e) {
                Log::warning("⚠️ Unable to clear reflection cache for {$fqcn}: {$e->getMessage()}");
            }
        });

        $this->routesByFile[$relativePath] = $routesForFile;
        $this->recordFile($filePath);

        $allRoutes = $this->flattenRoutesByFile();
        $compiled = $this->compileRouteFile($allRoutes);
        $this->writeFile($this->cacheFile, $compiled);
        $this->writeManifest($roots);

        if ($requireAfterBuild) {
            $this->requireCache();
            if (app()->environment(['local', 'development', 'testing'])) {
                $this->clearLaravelRouteCache();
            }

            if ($this->verboseLogging()) {
                $duration = number_format((microtime(true) - $startedAt) * 1000, 1);
                Log::info("✅ Updated route cache from {$relativePath} ({$duration} ms, ".count($routesForFile).' routes in file)');
            }
        }

        return [
            'routes' => count($routesForFile),
            'files' => count($this->routesByFile),
            'total_routes' => count($allRoutes),
        ];
    }

    /**
     * Remove cached route + manifest files.
     */
    public function clear(): void
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
        if (is_file($this->manifestFile)) {
            @unlink($this->manifestFile);
        }
    }

    public function cacheFilePath(): string
    {
        return $this->cacheFile;
    }

    public function manifestFilePath(): string
    {
        return $this->manifestFile;
    }

    /**
     * Ensure cached routes are available, rebuilding when stale.
     */
    public function ensureLoaded(): void
    {
        if ($this->commandRequiringBypass()) {
            return;
        }

        if ($this->isCacheFresh()) {
            $this->requireCache();

            return;
        }

        $this->build();
    }

    /**
     * Determine whether cached routes are still valid.
     */
    private function isCacheFresh(): bool
    {
        if (! is_file($this->cacheFile) || ! is_file($this->manifestFile)) {
            return false;
        }

        $manifest = $this->readManifest();
        if ($manifest === null) {
            return false;
        }

        $roots = (array) ($manifest['roots'] ?? []);
        if ($roots === []) {
            return false;
        }

        if (! array_key_exists('routes_by_file', $manifest)) {
            return false;
        }

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                return false;
            }
        }

        $directories = (array) ($manifest['directories'] ?? []);
        foreach ($directories as $relative => $mtime) {
            $absolute = $this->toAbsolutePath($relative);
            if (! is_dir($absolute)) {
                return false;
            }
            if ($this->safeFileMTime($absolute) !== $mtime) {
                return false;
            }
        }

        $files = (array) ($manifest['files'] ?? []);
        foreach ($files as $relative => $mtime) {
            $absolute = $this->toAbsolutePath($relative);
            if (! is_file($absolute)) {
                return false;
            }
            if ($this->safeFileMTime($absolute) !== $mtime) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function discoveryRoots(): array
    {
        $configured = config('zolta-http.routes.paths', []);
        $roots = [];

        foreach ((array) $configured as $path) {
            // Support glob patterns (e.g., app_path('Services/*/API/Controllers'))
            if (str_contains((string) $path, '*') || str_contains((string) $path, '?') || str_contains((string) $path, '[')) {
                $globbed = glob($path, GLOB_ONLYDIR);
                if ($globbed !== false) {
                    foreach ($globbed as $matchedPath) {
                        $real = realpath($matchedPath);
                        if ($real !== false && is_dir($real)) {
                            $roots[] = $real;
                        }
                    }
                }
            } else {
                // Regular path
                $real = realpath($path);
                if ($real !== false && is_dir($real)) {
                    $roots[] = $real;
                }
            }
        }

        if ($roots !== []) {
            return array_values(array_unique($roots));
        }

        $default = realpath($this->servicesPath);

        return $default !== false ? [$default] : [];
    }

    /**
     * Generate the PHP file containing the cached routes.
     *
     * @param  list<array{code:string,middleware:array<int, string>|string}|string>  $routes
     */
    private function compileRouteFile(array $routes): string
    {
        if ($routes === []) {
            return <<<'PHP'
<?php
// No attribute routes discovered.
PHP;
        }

        $apiRoutes = [];
        $webRoutes = [];
        $otherRoutes = [];

        foreach ($routes as $route) {
            if (is_array($route)) {
                $code = $route['code'] ?? '';
                $middleware = $route['middleware'] ?? [];
            } else {
                $code = (string) $route;
                $middleware = [];
            }

            $middleware = array_map(strval(...), (array) $middleware);
            $hasApi = in_array('api', $middleware, true);
            $hasWeb = in_array('web', $middleware, true);

            if ($hasApi) {
                $apiRoutes[] = $code;
            } elseif ($hasWeb) {
                $webRoutes[] = $code;
            } else {
                $otherRoutes[] = $code;
            }
        }

        $sections = [];

        if ($otherRoutes !== []) {
            $sections[] = implode("\n\n", $otherRoutes);
        }

        if ($webRoutes !== []) {
            $sections[] = "Route::middleware('web')->group(function (): void {\n".
                $this->indent(implode("\n\n", $webRoutes))."\n});";
        }

        if ($apiRoutes !== []) {
            $sections[] = "Route::middleware('api')->prefix('api')->group(function (): void {\n".
                $this->indent(implode("\n\n", $apiRoutes))."\n});";
        }

        $body = implode("\n\n", $sections);

        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

{$body}

PHP;
    }

    /**
     * @return list<string>
     */
    private function flattenRoutesByFile(): array
    {
        $flattened = [];
        $seen = [];

        foreach ($this->routesByFile as $routes) {
            foreach ((array) $routes as $route) {
                $key = is_array($route)
                    ? ($route['code'] ?? md5(serialize($route)))
                    : (string) $route;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $flattened[] = $route;
            }
        }

        // return RouteRegistrationSorter::sort($flattened);
        return $flattened;
    }

    /**
     * @return array{
     *     version:int,
     *     generated_at:int,
     *     roots: list<string>,
     *     files: array<string, int>,
     *     directories: array<string, int>,
     *     routes_by_file: array<string, list<array{code:string,middleware:array<int, string>|string}|string>>
     * }|null
     */
    private function readManifest(): ?array
    {
        if (! is_file($this->manifestFile)) {
            return null;
        }

        $manifest = @include $this->manifestFile;
        if (! is_array($manifest)) {
            return null;
        }

        if (($manifest['version'] ?? null) !== self::MANIFEST_VERSION) {
            return null;
        }

        return $manifest;
    }

    private function requireCache(): void
    {
        try {
            require $this->cacheFile;
            if ($this->verboseLogging()) {
                Log::debug('✅ Attribute routes loaded from cache.');
            }
        } catch (\Throwable $e) {
            $message = "⚠️ Unable to load attribute route cache: {$e->getMessage()}";

            if (app()->runningInConsole()) {
                if ($this->verboseLogging()) {
                    Log::warning($message);
                }

                return;
            }

            throw $e;
        }
    }

    /**
     * @param  list<string>  $roots
     */
    private function writeManifest(array $roots): void
    {
        ksort($this->files);
        ksort($this->directories);
        ksort($this->routesByFile);

        $payload = [
            'version' => self::MANIFEST_VERSION,
            'generated_at' => time(),
            'roots' => array_values($roots),
            'files' => $this->files,
            'directories' => $this->directories,
            'routes_by_file' => $this->routesByFile,
        ];

        $contents = "<?php\n\nreturn ".var_export($payload, true).";\n";
        $this->writeFile($this->manifestFile, $contents);
    }

    private function indent(string $body): string
    {
        $lines = explode("\n", $body);
        $lines = array_map(static fn (string $line): string => '    '.$line, $lines);

        return implode("\n", $lines);
    }

    private function recordFile(string $absolutePath): void
    {
        $relative = $this->toRelativePath($absolutePath);
        $this->files[$relative] = $this->safeFileMTime($absolutePath);

        $dir = dirname($absolutePath);
        $this->recordDirectory($dir);
    }

    private function recordDirectory(string $absolutePath): void
    {
        $absolutePath = rtrim($absolutePath, DIRECTORY_SEPARATOR);
        if (! Str::startsWith($absolutePath, $this->servicesPath)) {
            // Avoid recording outside of discovery roots
            return;
        }

        while (Str::startsWith($absolutePath, $this->servicesPath)) {
            $relative = $this->toRelativePath($absolutePath);
            if (! isset($this->directories[$relative])) {
                $this->directories[$relative] = $this->safeFileMTime($absolutePath);
            }

            if ($absolutePath === $this->servicesPath) {
                break;
            }

            $absolutePath = dirname($absolutePath);
        }
    }

    private function toRelativePath(string $absolute): string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $base = str_replace('\\', '/', base_path());

        if (Str::startsWith($absolute, $base.'/')) {
            return Str::after($absolute, $base.'/');
        }

        return ltrim($absolute, '/');
    }

    private function toAbsolutePath(string $relative): string
    {
        return base_path($relative);
    }

    private function safeFileMTime(string $path): int
    {
        $mtime = @filemtime($path);

        return $mtime === false ? 0 : (int) $mtime;
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $contents, LOCK_EX);
    }

    private function commandRequiringBypass(): ?string
    {
        if (! app()->runningInConsole()) {
            return null;
        }

        $command = $this->resolveConsoleCommand();
        if ($command === null) {
            return null;
        }

        $configured = (array) config('zolta-http.routes.cache.skip_commands', []);
        $configured[] = 'make:zolta-update-namespace';
        $configured[] = 'package:discover';

        $patterns = array_filter(array_unique(array_map(
            static fn ($value): string => (string) $value,
            $configured
        )));

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if ($pattern === $command || Str::is($pattern, $command)) {
                return $command;
            }
        }

        return null;
    }

    private function resolveConsoleCommand(): ?string
    {
        $argv = $_SERVER['argv'] ?? null;
        if (! is_array($argv) || $argv === []) {
            return null;
        }

        $arguments = array_values(array_filter(
            $argv,
            static fn ($value): bool => is_string($value) && $value !== ''
        ));

        if ($arguments === []) {
            return null;
        }

        $artisanIndex = $this->indexOfArtisanBinary($arguments);
        if ($artisanIndex !== null) {
            $candidate = $arguments[$artisanIndex + 1] ?? null;

            return $this->normaliseConsoleCommand($candidate);
        }

        if (count($arguments) > 1) {
            return $this->normaliseConsoleCommand($arguments[1]);
        }

        return null;
    }

    private function normaliseConsoleCommand(?string $command): ?string
    {
        if ($command === null || $command === '' || str_starts_with($command, '-')) {
            return null;
        }

        return $command;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function indexOfArtisanBinary(array $arguments): ?int
    {
        foreach ($arguments as $index => $argument) {
            $basename = basename((string) $argument);
            if ($basename === 'artisan') {
                return $index;
            }
        }

        return null;
    }

    private function verboseLogging(): bool
    {
        return (bool) config('zolta.options.verbose_logging', false);
    }

    private function clearLaravelRouteCache(): void
    {
        // Clear Laravel's compiled routes cache to force reload
        $routesCachePath = base_path('bootstrap/cache/routes-v7.php');
        if (file_exists($routesCachePath)) {
            @unlink($routesCachePath);
        }

        // Also clear any other route caches
        $routeCachePath = base_path('bootstrap/cache/route-v7.php');
        if (file_exists($routeCachePath)) {
            @unlink($routeCachePath);
        }

        if ($this->verboseLogging()) {
            Log::info('🧹 Cleared Laravel route cache to force reload');
        }
    }
}
