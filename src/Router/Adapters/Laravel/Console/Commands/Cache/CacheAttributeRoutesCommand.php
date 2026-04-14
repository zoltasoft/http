<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Console\Commands\Cache;

use Illuminate\Console\Command;
use Zolta\Http\Router\Laravel\Bootstrap\AttributeRouteCache;

class CacheAttributeRoutesCommand extends Command
{
    protected $signature = 'zolta:routes:cache
                            {--file= : Process only a specific controller file}';

    protected $description = 'Scans attribute-based controllers and caches their routes in bootstrap/cache/attribute_routes.php';

    public function handle(): int
    {
        $attributeRouteCache = new AttributeRouteCache;

        if ($file = $this->option('file')) {
            // Single file mode
            $result = $attributeRouteCache->buildSingleFile($file, false);

            if (($result['routes'] ?? 0) === 0 && ($result['total_routes'] ?? 0) === 0) {
                $this->warn("⚠️ No attribute routes found in {$file}.");

                return Command::FAILURE;
            }

            $this->info(sprintf(
                '✅ Route cache updated for %s (%d routes, %d total in cache).',
                $file,
                $result['routes'],
                $result['total_routes'] ?? $result['routes']
            ));
        } else {
            // Full rebuild mode
            $result = $attributeRouteCache->build(false);

            if ($result['files'] === 0 && ! file_exists($attributeRouteCache->cacheFilePath())) {
                $this->warn('⚠️ No attribute routes discovered – nothing cached.');

                return Command::FAILURE;
            }

            $this->info(sprintf(
                '✅ Attribute routes cached to %s (%d routes from %d files).',
                $attributeRouteCache->cacheFilePath(),
                $result['routes'],
                $result['files']
            ));
        }

        return Command::SUCCESS;
    }
}
