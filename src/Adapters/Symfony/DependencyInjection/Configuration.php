<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Zolta\Http\Symfony\Controllers\Controller;

final class Configuration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('zolta');

        $arrayNodeDefinition = $treeBuilder->getRootNode();

        $arrayNodeDefinition
            ->children()
            ->arrayNode('commands')
            ->scalarPrototype()->end()
            ->defaultValue(['%kernel.project_dir%/src/Services'])
            ->end()
            ->arrayNode('queries')
            ->scalarPrototype()->end()
            ->defaultValue(['%kernel.project_dir%/src/Services'])
            ->end()
            ->arrayNode('infrastructure_events')
            ->scalarPrototype()->end()
            ->defaultValue(['%kernel.project_dir%/src/Services'])
            ->end()
            ->arrayNode('cache')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('command')->defaultValue('%kernel.project_dir%/var/cache/zolta_command_map.php')->end()
            ->scalarNode('query')->defaultValue('%kernel.project_dir%/var/cache/zolta_query_map.php')->end()
            ->scalarNode('event')->defaultValue('%kernel.project_dir%/var/cache/zolta_event_map.php')->end()
            ->end()
            ->end()
            ->arrayNode('cache_manifest')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('command')->defaultValue('%kernel.project_dir%/var/cache/zolta_command_map_manifest.php')->end()
            ->scalarNode('query')->defaultValue('%kernel.project_dir%/var/cache/zolta_query_map_manifest.php')->end()
            ->scalarNode('event')->defaultValue('%kernel.project_dir%/var/cache/zolta_event_map_manifest.php')->end()
            ->end()
            ->end()
            ->arrayNode('map_cache')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->arrayNode('auto_refresh_env')
            ->scalarPrototype()->end()
            ->defaultValue(['dev', 'test'])
            ->end()
            ->end()
            ->end()
            ->arrayNode('map_keys')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('command')->defaultValue('command.map')->end()
            ->scalarNode('query')->defaultValue('query.map')->end()
            ->scalarNode('event')->defaultValue('event.map')->end()
            ->end()
            ->end()
            ->arrayNode('options')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('auto_detect_psr4')->defaultTrue()->end()
            ->booleanNode('write_atomic')->defaultTrue()->end()
            ->scalarNode('file_pattern')->defaultValue('*.php')->end()
            ->arrayNode('exclude_paths')
            ->scalarPrototype()->end()
            ->defaultValue([
                '**/Persistence/Seeders/**',
                '**/Persistence/Factories/**',
                '**/Persistence/Migrations/**',
                '**/Infrastructure/Persistence/Migrations/**',
                '**/Infrastructure/Repositories/**',
                '**/API/Routes/**',
                '**/Database/**',
                '**/vendor/**',
            ])
            ->end()
            ->scalarNode('composer_autoload')->defaultValue('%kernel.project_dir%/vendor/autoload.php')->end()
            ->booleanNode('follow_symlinks')->defaultFalse()->end()
            ->booleanNode('verbose_logging')->defaultFalse()->end()
            ->end()
            ->end()
            ->arrayNode('routes')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('cache')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->arrayNode('skip_commands')->scalarPrototype()->end()->defaultValue([])->end()
            ->end()
            ->end()
            ->arrayNode('paths')
            ->scalarPrototype()->end()
            ->defaultValue(['%kernel.project_dir%/src/**/Controllers'])
            ->end()
            ->scalarNode('default_response')->defaultValue(Controller::class)->end()
            ->end()
            ->end()
            ->arrayNode('auth')
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('abilities')
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->scalarPrototype()->end()
            ->end()
            ->end()
            ->arrayNode('user')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('class')->defaultNull()->end()
            ->arrayNode('attributes')
            ->scalarPrototype()->end()
            ->defaultValue(['permissions', 'role.permissions', 'roles.*.permissions'])
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('sqlite')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('wsl_path')->defaultNull()->end()
            ->scalarNode('windows_path')->defaultNull()->end()
            ->scalarNode('wsl_windows_path')->defaultNull()->end()
            ->scalarNode('browser_executable')->defaultNull()->end()
            ->scalarNode('backup_dir')->defaultNull()->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
