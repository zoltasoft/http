<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Support;

final readonly class CqrsContext
{
    /**
     * @param  array<int,string>  $commandPaths
     * @param  array<int,string>  $queryPaths
     * @param  array<int,string>  $eventPaths
     * @param  array<int,string>  $routePaths
     * @param  array<int,string>  $autoRefreshEnv
     */
    public function __construct(
        public string $projectDir,
        public array $commandPaths,
        public array $queryPaths,
        public array $eventPaths,
        public array $routePaths,
        public string $commandCache,
        public string $queryCache,
        public string $eventCache,
        public string $commandManifest,
        public string $queryManifest,
        public string $eventManifest,
        public bool $mapCacheEnabled,
        public array $autoRefreshEnv,
        public string $environment
    ) {}
}
