<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Console\Command\Sqlite;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'sqlite:openDB', description: 'Copy SQLite DB to Windows and open in DB Browser for SQLite.')]
final class ExportSqliteToWindowsCommand extends Command
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

        $source = $config['wsl_path'] ?? null;
        $wslWindowsPath = (string) ($config['wsl_windows_path'] ?? '/mnt/c/Users/Public/zolta-sqlite/database.sqlite');
        $realWindowsPath = (string) ($config['windows_path'] ?? 'C:\\Users\\Public\\zolta-sqlite\\database.sqlite');
        $dbBrowserPath = $config['browser_executable'] ?? null;

        if (! is_string($source) || $source === '' || $wslWindowsPath === '') {
            $output->writeln('<error>Missing sqlite paths in zolta.sqlite (wsl_path or wsl_windows_path).</error>');

            return Command::FAILURE;
        }

        if (! $filesystem->exists($source)) {
            $output->writeln("<error>Database not found at: {$source}</error>");

            return Command::FAILURE;
        }

        // Ensure target directory exists
        $filesystem->mkdir(\dirname($wslWindowsPath), 0755);

        $filesystem->copy($source, $wslWindowsPath, true);
        $output->writeln(sprintf('<info>Copied database from %s to %s</info>', $source, $wslWindowsPath));

        if (is_string($dbBrowserPath) && $dbBrowserPath !== '' && $realWindowsPath !== '') {
            $cmd = sprintf(
                'cmd.exe /C start "" "%s" "%s" > /dev/null 2>&1 &',
                $dbBrowserPath,
                $realWindowsPath
            );
            @exec($cmd);
            $output->writeln(sprintf('<info>Launching DB Browser: %s</info>', $dbBrowserPath));
        } else {
            $output->writeln('<comment>browser_executable not configured; skipped auto-launch.</comment>');
        }

        return Command::SUCCESS;
    }
}
