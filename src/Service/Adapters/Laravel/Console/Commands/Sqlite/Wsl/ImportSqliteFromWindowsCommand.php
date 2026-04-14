<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sqlite:fetchDB')]
class ImportSqliteFromWindowsCommand extends Command
{
    protected $description = 'Import updated DB from Windows copy, with automatic backup of the current DB.';

    public function handle(Filesystem $filesystem): int
    {
        // 🔧 Load from config/zolta.php
        $config = config('zolta-http.sqlite');
        $target = $config['wsl_path']; // Laravel DB path inside WSL
        $windowsPath = $config['wsl_windows_path'] ?: '/mnt/c/Users/Public/zolta-sqlite/database.sqlite'; // WSL path for Windows DB copy
        $backupDir = $config['backup_dir'];
        if (! $filesystem->exists($windowsPath)) {
            $this->error("❌ No Windows copy found at: $windowsPath");

            return Command::FAILURE;
        }
        if (! $filesystem->isDirectory($backupDir)) {
            $filesystem->makeDirectory($backupDir, 0755, true);
        }
        if ($filesystem->exists($target)) {
            $timestamp = now()->format('Y-m-d_His');
            $backupPath = "$backupDir/database_before_import_{$timestamp}.sqlite";
            $filesystem->copy($target, $backupPath);
        }
        $filesystem->copy($windowsPath, $target);
        $this->info('Fetched successfully');

        return Command::SUCCESS;
    }
}
