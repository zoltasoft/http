<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl\CleanSqliteBackupsCommand;
use Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl\ExportSqliteToWindowsCommand;
use Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl\ImportSqliteFromWindowsCommand;

class ZoltaCommandServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportSqliteToWindowsCommand::class,
                ImportSqliteFromWindowsCommand::class,
                CleanSqliteBackupsCommand::class,
            ]);
        }
    }
}
