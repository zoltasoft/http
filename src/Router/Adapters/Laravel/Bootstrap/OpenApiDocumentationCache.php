<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Bootstrap;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Incrementally builds an OpenAPI export from controller attributes using the
 * same file-tracking strategy as the attribute route cache.
 */
final class OpenApiDocumentationCache
{
    private const MANIFEST_VERSION = 1;

    private readonly string $servicesPath;

    private readonly string $outputFile;

    private readonly string $manifestFile;

    /** @var array<string,int> */
    private array $files = [];

    /** @var array<string,int> */
    private array $directories = [];

    /** @var array<string,list<array{path:string,method:string,operation:array<string,mixed>}>> */
    private array $operationsByFile = [];

    /** @var array<string,array<string,array<string,mixed>>> */
    private array $schemasByFile = [];

    public function __construct(?string $servicesPath = null)
    {
        $this->servicesPath = $servicesPath ?? app_path('Services');

        $outputDir = (string) config('zolta-http.routes.documentation.output_dir', base_path('bootstrap/cache'));
        $outputFile = (string) config('zolta-http.routes.documentation.output_file', 'openapi.json');
        $manifestFile = (string) config('zolta-http.routes.documentation.manifest_file', 'openapi_manifest.php');

        $this->outputFile = $this->resolveOutputPath($outputDir, $outputFile);
        $this->manifestFile = $this->resolveOutputPath($outputDir, $manifestFile);
    }

    public function enabled(): bool
    {
        return (bool) config('zolta-http.routes.documentation.enabled', false);
    }

    /**
     * @return array{operations:int,files:int}
     */
    public function build(): array
    {
        if (! $this->enabled()) {
            return ['operations' => 0, 'files' => 0];
        }

        $roots = $this->discoveryRoots();
        if ($roots === []) {
            return ['operations' => 0, 'files' => 0];
        }

        $this->files = [];
        $this->directories = [];
        $this->operationsByFile = [];
        $this->schemasByFile = [];

        foreach ($roots as $root) {
            $finder = new Finder;
            $finder->files()->in($root)->name('*.php');

            foreach ($finder as $file) {
                $realPath = $file->getRealPath();
                if ($realPath === false || ! $this->shouldProcessFile($realPath)) {
                    continue;
                }

                $this->recordFile($realPath);
                $this->recordFragment($realPath);
            }

            $this->recordDirectory($root);
        }

        $this->writeCompiledDocument($roots);

        return [
            'operations' => count($this->flattenOperations()),
            'files' => count($this->operationsByFile),
        ];
    }

    /**
     * @return array{operations:int,files:int,total_operations:int}
     */
    public function buildSingleFile(string $filePath): array
    {
        if (! $this->enabled()) {
            return ['operations' => 0, 'files' => 0, 'total_operations' => 0];
        }

        if (! is_file($filePath) || ! is_readable($filePath) || ! $this->shouldProcessFile($filePath)) {
            return ['operations' => 0, 'files' => 0, 'total_operations' => count($this->flattenOperations())];
        }

        $manifest = $this->readManifest();
        if ($manifest === null) {
            $result = $this->build();

            return [
                'operations' => $result['operations'],
                'files' => $result['files'],
                'total_operations' => $result['operations'],
            ];
        }

        $this->files = (array) ($manifest['files'] ?? []);
        $this->directories = (array) ($manifest['directories'] ?? []);
        $this->operationsByFile = (array) ($manifest['operations_by_file'] ?? []);
        $this->schemasByFile = (array) ($manifest['schemas_by_file'] ?? []);
        $roots = (array) ($manifest['roots'] ?? []);

        if ($roots === []) {
            $result = $this->build();

            return [
                'operations' => $result['operations'],
                'files' => $result['files'],
                'total_operations' => $result['operations'],
            ];
        }

        $this->recordFile($filePath);
        $fragment = $this->fragmentForFile($filePath);
        $relativePath = $this->toRelativePath($filePath);
        $this->operationsByFile[$relativePath] = $fragment['operations'];
        $this->schemasByFile[$relativePath] = $fragment['schemas'];

        $this->writeCompiledDocument($roots);

        return [
            'operations' => count($fragment['operations']),
            'files' => count($this->operationsByFile),
            'total_operations' => count($this->flattenOperations()),
        ];
    }

    /**
     * Remove a single controller file from the cached documentation manifest
     * without rebuilding every controller.
     *
     * @return array{operations:int,files:int,total_operations:int}
     */
    public function removeFile(string $filePath): array
    {
        if (! $this->enabled()) {
            return ['operations' => 0, 'files' => 0, 'total_operations' => 0];
        }

        $manifest = $this->readManifest();
        if ($manifest === null) {
            $result = $this->build();

            return [
                'operations' => $result['operations'],
                'files' => $result['files'],
                'total_operations' => $result['operations'],
            ];
        }

        $this->files = (array) ($manifest['files'] ?? []);
        $this->directories = (array) ($manifest['directories'] ?? []);
        $this->operationsByFile = (array) ($manifest['operations_by_file'] ?? []);
        $this->schemasByFile = (array) ($manifest['schemas_by_file'] ?? []);
        $roots = (array) ($manifest['roots'] ?? []);

        if ($roots === []) {
            $result = $this->build();

            return [
                'operations' => $result['operations'],
                'files' => $result['files'],
                'total_operations' => $result['operations'],
            ];
        }

        $relativePath = $this->toRelativePath($filePath);
        $removedOperations = count($this->operationsByFile[$relativePath] ?? []);

        unset(
            $this->files[$relativePath],
            $this->operationsByFile[$relativePath],
            $this->schemasByFile[$relativePath],
        );

        $this->rebuildDirectories($roots);
        $this->writeCompiledDocument($roots);

        return [
            'operations' => $removedOperations,
            'files' => count($this->operationsByFile),
            'total_operations' => count($this->flattenOperations()),
        ];
    }

    public function clear(): void
    {
        if (is_file($this->outputFile)) {
            @unlink($this->outputFile);
        }

        if (is_file($this->manifestFile)) {
            @unlink($this->manifestFile);
        }
    }

    public function outputFilePath(): string
    {
        return $this->outputFile;
    }

    public function manifestFilePath(): string
    {
        return $this->manifestFile;
    }

    /**
     * @param  list<string>  $roots
     */
    private function writeCompiledDocument(array $roots): void
    {
        $document = OpenApiGenerator::compileDocument(
            $this->flattenOperations(),
            $this->flattenSchemas(),
        );

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $json = '{}';
        }

        $this->writeFile($this->outputFile, $json.PHP_EOL);
        $this->writeManifest($roots);
    }

    /**
     * @return array{
     *     operations:list<array{path:string,method:string,operation:array<string,mixed>}>,
     *     schemas:array<string,array<string,mixed>>
     * }
     */
    private function fragmentForFile(string $filePath): array
    {
        $controllerClass = AttributeRouteLoader::resolveControllerClassFromFile($filePath);
        if ($controllerClass === null) {
            return ['operations' => [], 'schemas' => []];
        }

        return OpenApiGenerator::generateForController($controllerClass, $filePath);
    }

    private function recordFragment(string $filePath): void
    {
        $relativePath = $this->toRelativePath($filePath);
        $fragment = $this->fragmentForFile($filePath);

        $this->operationsByFile[$relativePath] = $fragment['operations'];
        $this->schemasByFile[$relativePath] = $fragment['schemas'];
    }

    /**
     * @return list<array{path:string,method:string,operation:array<string,mixed>}>
     */
    private function flattenOperations(): array
    {
        $flattened = [];
        $seen = [];

        foreach ($this->operationsByFile as $operations) {
            foreach ($operations as $operation) {
                $key = strtolower($operation['method'].' '.$operation['path']);
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $flattened[] = $operation;
            }
        }

        usort($flattened, static function (array $left, array $right): int {
            $uriComparison = strcmp($left['path'], $right['path']);
            if ($uriComparison !== 0) {
                return $uriComparison;
            }

            return strcmp($left['method'], $right['method']);
        });

        return $flattened;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function flattenSchemas(): array
    {
        $schemas = [];

        foreach ($this->schemasByFile as $fragmentSchemas) {
            $schemas = array_replace($schemas, $fragmentSchemas);
        }

        ksort($schemas);

        return $schemas;
    }

    /**
     * @return array{
     *     version:int,
     *     generated_at:int,
     *     roots:list<string>,
     *     files:array<string,int>,
     *     directories:array<string,int>,
     *     operations_by_file:array<string,list<array{path:string,method:string,operation:array<string,mixed>}>>,
     *     schemas_by_file:array<string,array<string,array<string,mixed>>>
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

    /**
     * @param  list<string>  $roots
     */
    private function writeManifest(array $roots): void
    {
        ksort($this->files);
        ksort($this->directories);
        ksort($this->operationsByFile);
        ksort($this->schemasByFile);

        $payload = [
            'version' => self::MANIFEST_VERSION,
            'generated_at' => time(),
            'roots' => array_values($roots),
            'files' => $this->files,
            'directories' => $this->directories,
            'operations_by_file' => $this->operationsByFile,
            'schemas_by_file' => $this->schemasByFile,
        ];

        $contents = "<?php\n\nreturn ".var_export($payload, true).";\n";
        $this->writeFile($this->manifestFile, $contents);
    }

    /**
     * @return list<string>
     */
    private function discoveryRoots(): array
    {
        $configured = config('zolta-http.routes.paths', []);
        $roots = [];

        foreach ((array) $configured as $path) {
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

    private function shouldProcessFile(string $filePath): bool
    {
        $realPath = realpath($filePath) ?: $filePath;

        return AttributeRouteLoader::shouldProcessControllerFile($realPath);
    }

    private function recordFile(string $absolutePath): void
    {
        $relative = $this->toRelativePath($absolutePath);
        $this->files[$relative] = $this->safeFileMTime($absolutePath);

        $this->recordDirectory(dirname($absolutePath));
    }

    private function recordDirectory(string $absolutePath): void
    {
        $absolutePath = rtrim($absolutePath, DIRECTORY_SEPARATOR);
        if (! Str::startsWith($absolutePath, $this->servicesPath)) {
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

    /**
     * @param  list<string>  $roots
     */
    private function rebuildDirectories(array $roots): void
    {
        $this->directories = [];

        foreach ($roots as $root) {
            if (is_dir($root)) {
                $this->recordDirectory($root);
            }
        }

        foreach (array_keys($this->files) as $relativePath) {
            $absolutePath = base_path($relativePath);
            if (! is_file($absolutePath)) {
                unset($this->files[$relativePath]);
                continue;
            }

            $this->recordDirectory(dirname($absolutePath));
        }
    }

    private function resolveOutputPath(string $dir, string $file): string
    {
        $normalizedDir = $dir !== '' ? $dir : base_path('bootstrap/cache');

        return rtrim($normalizedDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($file, DIRECTORY_SEPARATOR);
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
}
