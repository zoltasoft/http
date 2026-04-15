<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands\Sqlite\Wsl;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sqlite:clean')]
class CleanSqliteBackupsCommand extends Command
{
    protected $description = 'Clean old SQLite backups, keeping only the three most recent ones.';

    public function handle(Filesystem $filesystem): int
    {
        $backupDir = config('zolta-http.sqlite.backup_dir', base_path('database/backups'));

        if (! $filesystem->isDirectory($backupDir)) {
            $this->warn("No backup directory found at: $backupDir");

            return Command::SUCCESS;
        }

        // Get all backups sorted by modified time (descending)
        $backups = collect($filesystem->files($backupDir))
            ->filter(fn ($file): bool => str_ends_with((string) $file->getFilename(), '.sqlite'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $count = $backups->count();

        if ($count <= 3) {
            $this->info("✅ Nothing to clean. Found only {$count} backup(s).");

            return Command::SUCCESS;
        }

        // Keep only the 3 most recent, delete the rest
        $toDelete = $backups->slice(3);
        foreach ($toDelete as $file) {
            $filesystem->delete($file->getPathname());
        }

        $this->info('🧹 Cleaned up '.$toDelete->count().' old backup(s).');
        $this->info("💾 Kept the 3 most recent backups in: $backupDir");

        return Command::SUCCESS;
    }
}
