<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Console\Command\Sqlite;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'sqlite:clean', description: 'Clean old SQLite backups, keeping only the three most recent ones.')]
final class CleanSqliteBackupsCommand extends Command
{
    /** @param array<string,mixed>|null $sqliteConfig */
    public function __construct(private readonly ?array $sqliteConfig = null)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem;
        $backupDir = $this->sqliteConfig['backup_dir'] ?? null;

        if (! is_string($backupDir) || $backupDir === '') {
            $output->writeln('<comment>No backup directory configured (zolta.sqlite.backup_dir).</comment>');

            return Command::SUCCESS;
        }

        if (! is_dir($backupDir)) {
            $output->writeln("<comment>No backup directory found at: {$backupDir}</comment>");

            return Command::SUCCESS;
        }

        $finder = (new Finder)
            ->files()
            ->in($backupDir)
            ->name('*.sqlite')
            ->sortByModifiedTime()
            ->reverseSorting();

        $backups = iterator_to_array($finder);
        $count = count($backups);

        if ($count <= 3) {
            $output->writeln("<info>Nothing to clean. Found only {$count} backup(s).</info>");

            return Command::SUCCESS;
        }

        $toDelete = array_slice($backups, 3);
        foreach ($toDelete as $file) {
            $filesystem->remove($file->getPathname());
        }

        $output->writeln(sprintf('<info>Cleaned up %d old backup(s). Kept the 3 most recent in: %s</info>', count($toDelete), $backupDir));

        return Command::SUCCESS;
    }
}
