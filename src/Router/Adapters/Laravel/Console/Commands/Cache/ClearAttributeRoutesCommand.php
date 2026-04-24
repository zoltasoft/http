<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Console\Commands\Cache;

use Illuminate\Console\Command;
use Zolta\Http\Router\Laravel\Bootstrap\AttributeRouteCache;

class ClearAttributeRoutesCommand extends Command
{
    protected $signature = 'zolta:routes:clear';

    protected $description = 'Clears the cached attribute routes from bootstrap/cache/attribute_routes.php and manifest';

    public function handle(): int
    {
        $attributeRouteCache = new AttributeRouteCache;
        $cachePath = $attributeRouteCache->cacheFilePath();
        $manifestPath = $attributeRouteCache->manifestFilePath();
        $documentationPath = $attributeRouteCache->documentationFilePath();
        $documentationManifestPath = $attributeRouteCache->documentationManifestFilePath();

        $cacheExists = file_exists($cachePath);
        $manifestExists = file_exists($manifestPath);
        $documentationExists = file_exists($documentationPath);
        $documentationManifestExists = file_exists($documentationManifestPath);

        if (! $cacheExists && ! $manifestExists && ! $documentationExists && ! $documentationManifestExists) {
            $this->info('ℹ️ No cached attribute routes found – nothing to clear.');

            return Command::SUCCESS;
        }

        $attributeRouteCache->clear();

        if ($cacheExists) {
            $this->info("🗑️ Cleared cached routes: {$cachePath}");
        }

        if ($manifestExists) {
            $this->info("🗑️ Cleared route manifest: {$manifestPath}");
        }

        if ($documentationExists) {
            $this->info("🗑️ Cleared OpenAPI documentation: {$documentationPath}");
        }

        if ($documentationManifestExists) {
            $this->info("🗑️ Cleared OpenAPI manifest: {$documentationManifestPath}");
        }

        $this->info('✅ Attribute route cache cleared successfully.');

        return Command::SUCCESS;
    }
}
