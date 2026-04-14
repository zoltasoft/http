<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands\Cache;

use Illuminate\Console\Command;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Zolta\Http\Router\Cache\ReflectionCache;

class PrewarmReflectionCommand extends Command
{
    protected $signature = 'zolta:cache:reflect
    {--path= : Path to scan (defaults to app/Services/* if omitted)}
    {--namespace= : Namespace filter (auto-inferred if omitted)}
    {--controllers : Limit to Controllers only}
    {--clear : Clear cache before prewarm}
    {--dry-run : Simulate without writing cache}';

    protected $description = 'Prewarm ReflectionCache for all Zolta-based service classes';

    public function handle(): int
    {
        $this->title();

        $path = $this->option('path') ?? app_path('Services');
        $paths = $this->resolvePaths($path);

        $totalClasses = 0;
        $totalSkipped = 0;

        foreach ($paths as $servicePath) {
            [$count, $skipped] = $this->processService($servicePath);
            $totalClasses += $count;
            $totalSkipped += $skipped;
        }

        $this->line('');
        $this->info('✨ Done. ReflectionCache warmed successfully.');
        $this->info("📊 Total processed: {$totalClasses} classes, skipped: {$totalSkipped}");

        return Command::SUCCESS;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function processService(string $servicePath): array
    {
        $namespace = $this->option('namespace');
        $isAuto = false;

        if (! $namespace) {
            $namespace = $this->inferNamespaceFromPath($servicePath);
            $isAuto = true;
        }

        $this->line("🔍 Scanning: {$servicePath}");
        $this->line('📦 Namespace filter: '.($isAuto ? '(auto)' : $namespace));

        if ($this->option('clear')) {
            ReflectionCache::clearRuntimeCache();
            $this->line('🧹 Cleared cache');
        }

        $finder = new Finder;
        $finder->files()->in($servicePath)->name('*.php');

        $count = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');
        $controllersOnly = $this->option('controllers');

        foreach ($finder as $file) {
            $fqcn = $this->extractClass($file->getRealPath());
            if (! $fqcn) {
                $skipped++;
                $this->line('⚠️  Skipping (no class found): '.$file->getRelativePathname());

                continue;
            }

            if ($controllersOnly && ! str_contains($fqcn, 'Controller')) {
                $skipped++;

                continue;
            }

            if ($namespace) {
                $nsFilter = trim(str_replace(['/', '\\\\'], '\\', $namespace), '\\');
                $fqcnNorm = trim(str_replace(['/', '\\\\'], '\\', $fqcn), '\\');

                if (stripos($fqcnNorm, $nsFilter) !== 0) {
                    $skipped++;

                    continue;
                }
            }

            try {
                $count++;
                if (! $dryRun) {
                    ReflectionCache::clear($fqcn);
                    $ref = new ReflectionClass($fqcn);
                    ReflectionCache::warm($ref);
                }
                if (! $this->option('quiet')) {
                    $this->line("- {$fqcn}");
                }
            } catch (\Throwable $e) {
                $this->error("❌ {$fqcn}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Found {$count} classes to process. Skipped {$skipped}.\n");

        return [$count, $skipped];
    }

    private function extractClass(string $path): ?string
    {
        $contents = file_get_contents($path);
        if (! preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
            return null;
        }
        if (! preg_match('/class\s+(\w+)/', $contents, $c)) {
            return null;
        }

        return trim($m[1]).'\\'.trim($c[1]);
    }

    private function inferNamespaceFromPath(string $path): string
    {
        $appPath = realpath(app_path());
        $realPath = realpath($path);
        $relative = str_replace($appPath, '', $realPath);
        $relative = trim(str_replace(['/', '\\'], '\\', $relative), '\\');

        if (str_starts_with($relative, 'Services\\')) {
            $relative = substr($relative, strlen('Services\\'));
        }

        return 'App\\'.$relative;
    }

    /**
     * @return list<string>
     */
    private function resolvePaths(string $path): array
    {
        if (is_dir($path) && basename($path) === 'Services') {
            $dirs = glob($path.'/*', GLOB_ONLYDIR);
            if ($dirs !== [] && $dirs !== false) {
                return $dirs;
            }
        }

        return [rtrim($path, DIRECTORY_SEPARATOR)];
    }

    private function title(): void
    {
        $this->line('========================================================');
        $this->info('⚡ Zolta Reflection Cache Prewarmer');
        $this->line('========================================================');
    }
}
