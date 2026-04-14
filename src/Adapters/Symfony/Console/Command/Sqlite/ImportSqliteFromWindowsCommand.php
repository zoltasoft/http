<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Console\Command\Sqlite;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'sqlite:fetchDB', description: 'Import updated DB from Windows copy, with automatic backup of the current DB.')]
final class ImportSqliteFromWindowsCommand extends Command
{
    /** @param array<string,mixed>|null $sqliteConfig */
    public function __construct(private readonly ?array $sqliteConfig = null)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem;
        $config = $this->sqliteConfig ?? [];

        $target = $config['wsl_path'] ?? null;
        $windowsPathRaw = $config['wsl_windows_path'] ?? null;
        $windowsPath = is_string($windowsPathRaw) && $windowsPathRaw !== ''
            ? $windowsPathRaw
            : '/mnt/c/Users/Public/zolta-sqlite/database.sqlite';
        $backupDir = $config['backup_dir'] ?? null;

        if (! is_string($target) || $target === '') {
            $output->writeln('<error>Missing sqlite paths in zolta.sqlite (wsl_path or wsl_windows_path).</error>');

            return Command::FAILURE;
        }

        if (! $filesystem->exists($windowsPath)) {
            $output->writeln("<error>No Windows copy found at: {$windowsPath}</error>");

            return Command::FAILURE;
        }

        if (is_string($backupDir) && $backupDir !== '') {
            if (! $filesystem->exists($backupDir)) {
                $filesystem->mkdir($backupDir, 0755);
            }

            if ($filesystem->exists($target)) {
                $timestamp = (new \DateTimeImmutable)->format('Y-m-d_His');
                $backupPath = rtrim($backupDir, '/\\').DIRECTORY_SEPARATOR."database_before_import_{$timestamp}.sqlite";
                $filesystem->copy($target, $backupPath, true);
            }
        }

        $filesystem->copy($windowsPath, $target, true);
        $output->writeln('<info>Fetched successfully.</info>');

        return Command::SUCCESS;
    }
}
