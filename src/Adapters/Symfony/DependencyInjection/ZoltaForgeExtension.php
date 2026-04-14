<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

// Declare without hard Symfony dependencies to keep the package installable without Symfony.
if (class_exists(Extension::class)) {
    class ZoltaForgeExtension extends Extension
    {
        /**
         * @param  array<int, array<string, mixed>>  $configs
         */
        public function load(array $configs, ContainerBuilder $container): void
        {
            $configuration = new Configuration;
            $config = $this->processConfiguration($configuration, $configs);

            $container->setParameter('zolta.map.paths.commands', $config['commands']);
            $container->setParameter('zolta.map.paths.queries', $config['queries']);
            $container->setParameter('zolta.map.paths.events', $config['infrastructure_events']);

            $container->setParameter('zolta.cache.command', $config['cache']['command']);
            $container->setParameter('zolta.cache.query', $config['cache']['query']);
            $container->setParameter('zolta.cache.event', $config['cache']['event']);

            $container->setParameter('zolta.cache_manifest.command', $config['cache_manifest']['command']);
            $container->setParameter('zolta.cache_manifest.query', $config['cache_manifest']['query']);
            $container->setParameter('zolta.cache_manifest.event', $config['cache_manifest']['event']);

            $container->setParameter('zolta.map.cache.enabled', $config['map_cache']['enabled']);
            $container->setParameter('zolta.map.cache.auto_refresh_env', $config['map_cache']['auto_refresh_env']);

            $container->setParameter('zolta.map_keys.command', $config['map_keys']['command']);
            $container->setParameter('zolta.map_keys.query', $config['map_keys']['query']);
            $container->setParameter('zolta.map_keys.event', $config['map_keys']['event']);

            $container->setParameter('zolta.routes.paths', $config['routes']['paths']);
            $container->setParameter('zolta.routes.cache.enabled', $config['routes']['cache']['enabled']);
            $container->setParameter('zolta.routes.cache.skip_commands', $config['routes']['cache']['skip_commands']);
            $container->setParameter('zolta.routes.default_response', $config['routes']['default_response']);

            $container->setParameter('zolta.options.exclude_paths', $config['options']['exclude_paths']);
            $container->setParameter('zolta.options.auto_detect_psr4', $config['options']['auto_detect_psr4']);
            $container->setParameter('zolta.options.write_atomic', $config['options']['write_atomic']);
            $container->setParameter('zolta.options.file_pattern', $config['options']['file_pattern']);
            $container->setParameter('zolta.options.composer_autoload', $config['options']['composer_autoload']);
            $container->setParameter('zolta.options.follow_symlinks', $config['options']['follow_symlinks']);
            $container->setParameter('zolta.options.verbose_logging', $config['options']['verbose_logging']);
            $container->setParameter('zolta.security', $config['auth']);
            $container->setParameter('zolta.sqlite', $config['sqlite']);

            // Ensure autoconfiguration rules exist before services are loaded.
            SymfonyRegistrar::registerAutoconfiguration($container);
        }

        public function getAlias(): string
        {
            return 'zolta_forge';
        }
    }
} else {
    // Fallback no-op to avoid hard dependency when Symfony is absent.
    class ZoltaForgeExtension
    {
        /**
         * @param  array<int, array<string, mixed>>  $configs
         */
        public function load(array $configs, mixed $container): void {}
    }
}
