<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Support;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ContextFactory
{
    public static function build(ContainerBuilder $containerBuilder): CqrsContext
    {
        $projectDir = $containerBuilder->hasParameter('kernel.project_dir') ? (string) $containerBuilder->getParameter('kernel.project_dir') : getcwd();

        $commandPaths = PathNormalizer::normalize((array) ($containerBuilder->hasParameter('zolta.map.paths.commands') ? $containerBuilder->getParameter('zolta.map.paths.commands') : [$projectDir.'/src']));
        $queryPaths = PathNormalizer::normalize((array) ($containerBuilder->hasParameter('zolta.map.paths.queries') ? $containerBuilder->getParameter('zolta.map.paths.queries') : [$projectDir.'/src']));
        $eventPaths = PathNormalizer::normalize((array) ($containerBuilder->hasParameter('zolta.map.paths.events') ? $containerBuilder->getParameter('zolta.map.paths.events') : [$projectDir.'/src']));
        $routePaths = PathNormalizer::normalize((array) ($containerBuilder->hasParameter('zolta.routes.paths') ? $containerBuilder->getParameter('zolta.routes.paths') : [$projectDir.'/src']));

        $commandCache = (string) ($containerBuilder->hasParameter('zolta.cache.command') ? $containerBuilder->getParameter('zolta.cache.command') : $projectDir.'/var/cache/zolta_command_map.php');
        $queryCache = (string) ($containerBuilder->hasParameter('zolta.cache.query') ? $containerBuilder->getParameter('zolta.cache.query') : $projectDir.'/var/cache/zolta_query_map.php');
        $eventCache = (string) ($containerBuilder->hasParameter('zolta.cache.event') ? $containerBuilder->getParameter('zolta.cache.event') : $projectDir.'/var/cache/zolta_event_map.php');

        $commandManifest = (string) ($containerBuilder->hasParameter('zolta.cache_manifest.command') ? $containerBuilder->getParameter('zolta.cache_manifest.command') : $projectDir.'/var/cache/zolta_command_map_manifest.php');
        $queryManifest = (string) ($containerBuilder->hasParameter('zolta.cache_manifest.query') ? $containerBuilder->getParameter('zolta.cache_manifest.query') : $projectDir.'/var/cache/zolta_query_map_manifest.php');
        $eventManifest = (string) ($containerBuilder->hasParameter('zolta.cache_manifest.event') ? $containerBuilder->getParameter('zolta.cache_manifest.event') : $projectDir.'/var/cache/zolta_event_map_manifest.php');

        $mapCacheEnabled = (bool) ($containerBuilder->hasParameter('zolta.map.cache.enabled') ? $containerBuilder->getParameter('zolta.map.cache.enabled') : true);
        $autoRefreshEnv = (array) ($containerBuilder->hasParameter('zolta.map.cache.auto_refresh_env') ? $containerBuilder->getParameter('zolta.map.cache.auto_refresh_env') : ['dev', 'test']);
        $environment = (string) ($containerBuilder->hasParameter('kernel.environment') ? $containerBuilder->getParameter('kernel.environment') : 'prod');

        return new CqrsContext(
            $projectDir,
            $commandPaths,
            $queryPaths,
            $eventPaths,
            $routePaths,
            $commandCache,
            $queryCache,
            $eventCache,
            $commandManifest,
            $queryManifest,
            $eventManifest,
            $mapCacheEnabled,
            $autoRefreshEnv,
            $environment
        );
    }
}
