<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zolta\Http\Symfony\Bootstrap\Routing\SymfonyRouteLoader;
use Zolta\Http\Symfony\Console\Command\CacheRoutesCommand;
use Zolta\Http\Symfony\Console\Command\Sqlite\CleanSqliteBackupsCommand;
use Zolta\Http\Symfony\Console\Command\Sqlite\ExportSqliteToWindowsCommand;
use Zolta\Http\Symfony\Console\Command\Sqlite\ImportSqliteFromWindowsCommand;
use Zolta\Http\Symfony\Console\Command\WatchRoutesCommand;
use Zolta\Http\Symfony\DependencyInjection\Support\CqrsContext;

final class ConsoleRegistrar
{
    public static function register(ContainerBuilder $containerBuilder, CqrsContext $cqrsContext): void
    {
        if (! class_exists(Command::class)) {
            return;
        }

        $containerBuilder->register(CacheRoutesCommand::class, CacheRoutesCommand::class)
            ->addArgument(new Reference(SymfonyRouteLoader::class))
            ->addArgument(new Reference('service_container'))
            ->addTag('console.command', ['command' => 'zolta:routes:cache']);

        $containerBuilder->register(WatchRoutesCommand::class, WatchRoutesCommand::class)
            ->addArgument(new Reference(SymfonyRouteLoader::class))
            ->addArgument(new Reference('service_container'))
            ->addTag('console.command', ['command' => 'zolta:routes:watch']);

        $containerBuilder->register(CleanSqliteBackupsCommand::class, CleanSqliteBackupsCommand::class)
            ->addArgument('%zolta.sqlite%')
            ->addTag('console.command', ['command' => 'sqlite:clean']);

        $containerBuilder->register(ExportSqliteToWindowsCommand::class, ExportSqliteToWindowsCommand::class)
            ->addArgument('%zolta.sqlite%')
            ->addTag('console.command', ['command' => 'sqlite:openDB']);

        $containerBuilder->register(ImportSqliteFromWindowsCommand::class, ImportSqliteFromWindowsCommand::class)
            ->addArgument('%zolta.sqlite%')
            ->addTag('console.command', ['command' => 'sqlite:fetchDB']);
    }
}
