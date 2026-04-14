<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sqlite:openDB')]
class ExportSqliteToWindowsCommand extends Command
{
    protected $description = 'Copy SQLite DB to Windows and open in DB Browser for SQLite.';

    public function handle(Filesystem $filesystem): int
    {
        $config = config('zolta-http.sqlite');
        $source = $config['wsl_path'];
        $wslWindowsPath = $config['wsl_windows_path'] ?: '/mnt/c/Users/Public/zolta-sqlite/database.sqlite';
        $realWindowsPath = $config['windows_path'] ?: 'C:\\Users\\Public\\zolta-sqlite\\database.sqlite';
        $dbBrowserPath = $config['browser_executable'] ?? null;

        if (! $filesystem->exists($source)) {
            $this->error("Database not found at: $source");

            return Command::FAILURE;
        }

        // Copy to Windows location (no backup this time)
        $filesystem->ensureDirectoryExists(dirname((string) $wslWindowsPath));
        $filesystem->copy($source, $wslWindowsPath);

        // Launch DB Browser directly on Windows path (non-blocking)
        if (is_string($dbBrowserPath) && $dbBrowserPath !== '') {
            $cmd = sprintf(
                'cmd.exe /C start "" "%s" "%s" > /dev/null 2>&1 &',
                $dbBrowserPath,
                $realWindowsPath
            );
            exec($cmd);
            $this->info("Copied database to: $wslWindowsPath");
            $this->info("Launching DB Browser: $dbBrowserPath");
        } else {
            $this->info("Copied database to: $wslWindowsPath");
            $this->warn('browser_executable not configured; skipping auto-launch.');
        }

        return Command::SUCCESS;
    }
}
