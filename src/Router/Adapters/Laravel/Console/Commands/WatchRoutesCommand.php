<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Zolta\Http\Router\Laravel\Bootstrap\AttributeRouteCache;

/**
 * Laravel artisan command that watches controller directories and rebuilds
 * cached attribute routes when files change.
 */
class WatchRoutesCommand extends Command
{
    protected $signature = 'zolta:routes:watch
                            {--poll=500 : Polling interval in milliseconds}';

    protected $description = 'Watch controller directories and automatically update attribute route cache on file changes';

    /** @var list<string> */
    private array $watchedPaths = [];

    /** @var array<string, string> */
    private array $fileHashes = [];

    private bool $isRunning = true;

    private readonly AttributeRouteCache $attributeRouteCache;

    public function __construct()
    {
        parent::__construct();
        $this->attributeRouteCache = new AttributeRouteCache;
    }

    public function handle(): int
    {
        $this->info('Starting Zolta Route Watcher...');
        $this->info('Watching controller directories for changes...');

        $this->primeAttributeRoutesIfMissing();

        if ($this->getOutput()->isVerbose()) {
            $this->line('Verbose mode enabled');
        }

        // Setup signal handling for graceful shutdown
        $this->setupSignalHandling();

        // Get paths to watch
        $this->watchedPaths = $this->getWatchedPaths();

        if ($this->watchedPaths === []) {
            $this->error('No controller directories found to watch.');
            $this->line('   Make sure zolta.zolta-router.routes.paths is configured in config/zolta/zolta-router.php');

            return Command::FAILURE;
        }

        // Display watched paths
        $this->displayWatchedPaths();

        // Initialize file hashes
        $this->initializeFileHashes();

        // Start watching loop
        return $this->startWatching();
    }

    private function setupSignalHandling(): void
    {
        // Handle Ctrl+C gracefully
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): void {
            $this->isRunning = false;
            $this->line('');
            $this->info('Received shutdown signal. Stopping watcher...');
        });
        pcntl_signal(SIGTERM, function (): void {
            $this->isRunning = false;
            $this->line('');
            $this->info('Received termination signal. Stopping watcher...');
        });
    }

    /**
     * @return list<string>
     */
    private function getWatchedPaths(): array
    {
        $configured = config('zolta-http.routes.paths', []);
        $paths = [];

        foreach ((array) $configured as $path) {
            // Support glob patterns
            if (str_contains((string) $path, '*') || str_contains((string) $path, '?') || str_contains((string) $path, '[')) {
                $globbed = glob($path, GLOB_ONLYDIR);
                if ($globbed !== false) {
                    foreach ($globbed as $matchedPath) {
                        $real = realpath($matchedPath);
                        if ($real !== false && is_dir($real)) {
                            $paths[] = $real;
                        }
                    }
                }
            } else {
                $real = realpath($path);
                if ($real !== false && is_dir($real)) {
                    $paths[] = $real;
                }
            }
        }

        // Fallback to default
        if ($paths === []) {
            $default = realpath(app_path('Services'));
            if ($default !== false && is_dir($default)) {
                $paths[] = $default;
            }
        }

        return array_values(array_unique($paths));
    }

    private function displayWatchedPaths(): void
    {
        $this->info('Watching ' . count($this->watchedPaths) . ' director' . (count($this->watchedPaths) === 1 ? 'y' : 'ies') . ':');

        foreach ($this->watchedPaths as $watchedPath) {
            $this->line('   - ' . $this->toRelativePath($watchedPath));
        }

        $this->line('');
    }

    private function initializeFileHashes(): void
    {
        $this->info('Initializing file tracking...');

        $finder = new Finder;
        $finder->files()
            ->name('*.php')
            ->in($this->watchedPaths)
            ->exclude($this->getExcludedDirectories());

        $count = 0;
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            if ($this->fileContainsClassLike($path)) {
                $this->fileHashes[$path] = $this->getFileHash($path);
                $count++;
            }
        }

        $this->info("✅ Tracking {$count} controller files");
        $this->line('');
    }

    private function startWatching(): int
    {
        $pollInterval = (int) $this->option('poll');
        $this->info("Polling every {$pollInterval}ms. Press Ctrl+C to stop.");
        $this->line('');

        $lastCheck = microtime(true);

        while ($this->isRunning) {
            $currentTime = microtime(true);

            // Check for changes
            if (($currentTime - $lastCheck) * 1000 >= $pollInterval) {
                $this->checkForChanges();
                $lastCheck = $currentTime;
            }

            // Small sleep to prevent CPU hogging
            usleep(10000); // 10ms
        }

        $this->info('👋 Route watcher stopped.');

        return Command::SUCCESS;
    }

    private function checkForChanges(): void
    {
        $finder = new Finder;
        $finder->files()
            ->name('*.php')
            ->in($this->watchedPaths)
            ->exclude($this->getExcludedDirectories());

        $currentFiles = [];
        $changes = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath();

            if (! $this->fileContainsClassLike($path)) {
                continue;
            }

            $currentFiles[$path] = true;

            // Check if file is new
            if (! isset($this->fileHashes[$path])) {
                $changes[] = ['type' => 'created', 'path' => $path];
                $this->fileHashes[$path] = $this->getFileHash($path);
            }
            // Check if file was modified
            elseif ($this->fileHashes[$path] !== $this->getFileHash($path)) {
                $changes[] = ['type' => 'modified', 'path' => $path];
                $this->fileHashes[$path] = $this->getFileHash($path);
            }
        }

        // Check for deleted files
        foreach (array_keys($this->fileHashes) as $path) {
            if (! isset($currentFiles[$path])) {
                $changes[] = ['type' => 'deleted', 'path' => $path];
                unset($this->fileHashes[$path]);
            }
        }

        // Process changes
        foreach ($changes as $change) {
            $this->processFileChange($change['type'], $change['path']);
        }
    }

    private function processFileChange(string $type, string $path): void
    {
        $relativePath = $this->toRelativePath($path);
        $filename = basename($path);

        switch ($type) {
            case 'created':
                $this->info("New controller: {$relativePath}");
                $this->updateRoutesForFile($path, 'created');
                break;

            case 'modified':
                $this->line("Modified controller: {$relativePath}");
                $this->updateRoutesForFile($path, 'modified');
                break;

            case 'deleted':
                $this->warn("Deleted controller: {$relativePath}");
                $this->removeRoutesForFile($path);
                break;
        }
    }

    private function updateRoutesForFile(string $path, string $action): void
    {
        try {
            $startTime = microtime(true);
            $process = $this->runCacheProcess(['zolta:routes:cache', '--file=' . $path]);
            $duration = number_format((microtime(true) - $startTime) * 1000, 1);

            if ($process->isSuccessful()) {
                $this->line("   ✅ Route cache updated ({$duration}ms)");
                $this->relayProcessOutput($process);
            } else {
                $this->error("   Failed to update routes ({$duration}ms)");
                $this->relayProcessOutput($process, true);
            }
        } catch (\Throwable $e) {
            $this->error("   Failed to update routes: {$e->getMessage()}");

            if ($this->getOutput()->isVerbose()) {
                Log::error("Route cache update failed for {$path}: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    private function removeRoutesForFile(string $path): void
    {
        try {
            // For deleted files, we need to rebuild the full cache to remove their routes
            $startTime = microtime(true);
            $process = $this->runCacheProcess(['zolta:routes:cache']);
            $duration = number_format((microtime(true) - $startTime) * 1000, 1);

            if ($process->isSuccessful()) {
                $this->info("   ✅ Route cache rebuilt ({$duration}ms)");
                $this->relayProcessOutput($process);
            } else {
                $this->error("   Failed to rebuild route cache ({$duration}ms)");
                $this->relayProcessOutput($process, true);
            }
        } catch (\Throwable $e) {
            $this->error("   Failed to rebuild route cache: {$e->getMessage()}");

            if ($this->getOutput()->isVerbose()) {
                Log::error("Route cache rebuild failed after file deletion: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runCacheProcess(array $arguments): Process
    {
        $command = array_merge([PHP_BINARY, base_path('artisan')], $arguments, ['--no-ansi', '--no-interaction']);

        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->run();

        return $process;
    }

    private function relayProcessOutput(Process $process, bool $forceError = false): void
    {
        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if ($forceError && $errorOutput !== '') {
            $this->line('   ' . $errorOutput);

            return;
        }

        if ($this->getOutput()->isVerbose() && $output !== '') {
            $this->line('   ' . $output);
        }

        if ($forceError && $output === '' && $errorOutput === '') {
            $this->line('   (no output from cache command)');
        }
    }

    private function primeAttributeRoutesIfMissing(): void
    {
        $cacheFile = $this->attributeRouteCache->cacheFilePath();
        $manifestFile = $this->attributeRouteCache->manifestFilePath();

        if (is_file($cacheFile) && is_file($manifestFile)) {
            return;
        }

        $this->line('Attribute route cache missing. Warming up...');

        $process = $this->runCacheProcess(['zolta:routes:cache']);

        if ($process->isSuccessful()) {
            $this->line('Attribute route cache generated.');
        } else {
            $this->error('Failed to generate attribute route cache before watching.');
            $this->relayProcessOutput($process, true);
            exit(Command::FAILURE);
        }
    }

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

    private function getFileHash(string $path): string
    {
        return md5_file($path) ?: '';
    }

    /**
     * @return list<string>
     */
    private function getExcludedDirectories(): array
    {
        $configured = config('zolta-http.routes.exclude_paths', []);

        // Convert glob patterns to directory names where possible
        $excluded = [];
        foreach ($configured as $pattern) {
            // Simple conversion for common patterns
            if (str_contains((string) $pattern, '/Seeders/')) {
                $excluded[] = 'Seeders';
            }
            if (str_contains((string) $pattern, '/Factories/')) {
                $excluded[] = 'Factories';
            }
            if (str_contains((string) $pattern, '/Migrations/')) {
                $excluded[] = 'Migrations';
            }
            if (str_contains((string) $pattern, '/Routes/')) {
                $excluded[] = 'Routes';
            }
            if (str_contains((string) $pattern, '/Database/')) {
                $excluded[] = 'Database';
            }
        }

        return $excluded;
    }

    private function toRelativePath(string $absolute): string
    {
        $base = base_path();
        if (Str::startsWith($absolute, $base . '/')) {
            return Str::after($absolute, $base . '/');
        }

        return $absolute;
    }
}
