<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Zolta\Http\Symfony\Bootstrap\Routing\SymfonyRouteLoader;

final class WatchRoutesCommand extends Command
{
    /** @var array<string,string> */
    private array $fileHashes = [];

    private bool $running = true;

    public function __construct(
        private readonly SymfonyRouteLoader $symfonyRouteLoader,
        private readonly ContainerInterface $container
    ) {
        parent::__construct('zolta:routes:watch');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Watch controller directories and rebuild attribute routes when files change.')
            ->addOption('poll', null, InputOption::VALUE_OPTIONAL, 'Polling interval in milliseconds', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $this->resolveWatchedPaths();
        if ($paths === []) {
            $output->writeln('<error>No controller directories found to watch. Configure zolta.routes.paths.</error>');

            return Command::FAILURE;
        }

        $this->setupSignalHandling($output);
        $pollInterval = max(100, (int) $input->getOption('poll'));

        $output->writeln('Watching '.count($paths).' director'.(count($paths) === 1 ? 'y' : 'ies').' (poll '.$pollInterval.'ms)...');
        $this->primeHashes($paths);

        while ($this->running) {
            usleep($pollInterval * 1000);
            $changes = $this->detectChanges($paths);
            if ($changes !== []) {
                $this->rebuildRoutes($output, $paths, $changes);
            }
        }

        $output->writeln('👋 Route watcher stopped.');

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveWatchedPaths(): array
    {
        $configured = (array) $this->container->getParameter('zolta.routes.paths');
        $paths = [];

        foreach ($configured as $path) {
            if (strpbrk((string) $path, '*?[') !== false) {
                $globbed = glob((string) $path, GLOB_ONLYDIR);
                if ($globbed !== false) {
                    foreach ($globbed as $p) {
                        $real = realpath($p);
                        if ($real !== false && is_dir($real)) {
                            $paths[] = $real;
                        }
                    }
                }
            } else {
                $real = realpath((string) $path);
                if ($real !== false && is_dir($real)) {
                    $paths[] = $real;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $paths
     */
    private function primeHashes(array $paths): void
    {
        $finder = new Finder;
        $finder->files()->name('*.php')->in($paths);

        foreach ($finder as $file) {
            $real = $file->getRealPath();
            if ($real !== false) {
                $this->fileHashes[$real] = $this->hash($real);
            }
        }
    }

    /**
     * @param  list<string>  $paths
     * @return array<int,string>
     */
    private function detectChanges(array $paths): array
    {
        $finder = new Finder;
        $finder->files()->name('*.php')->in($paths);

        $current = [];
        $changes = [];

        foreach ($finder as $file) {
            $real = $file->getRealPath();
            if ($real === false) {
                continue;
            }

            $current[$real] = true;
            $hash = $this->hash($real);

            if (! isset($this->fileHashes[$real])) {
                $changes[] = $real;
            } elseif ($this->fileHashes[$real] !== $hash) {
                $changes[] = $real;
            }

            $this->fileHashes[$real] = $hash;
        }

        foreach (array_keys($this->fileHashes) as $path) {
            if (! isset($current[$path])) {
                $changes[] = $path;
                unset($this->fileHashes[$path]);
            }
        }

        return $changes;
    }

    /**
     * @param  list<string>  $paths
     * @param  array<int,string>  $changes
     */
    private function rebuildRoutes(OutputInterface $output, array $paths, array $changes): void
    {
        $output->writeln('↻ Change detected ('.count($changes).' files). Rebuilding routes...');

        try {
            $collection = $this->symfonyRouteLoader->buildCollection($paths);
            $output->writeln(sprintf('   ✅ Rebuilt route collection (%d routes)', count($collection)));
        } catch (\Throwable $e) {
            $output->writeln('<error>   ❌ Failed to rebuild routes: '.$e->getMessage().'</error>');
        }
    }

    private function hash(string $path): string
    {
        return md5_file($path) ?: '';
    }

    private function setupSignalHandling(OutputInterface $output): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
        });
        if (defined('SIGTERM')) {
            pcntl_signal(SIGTERM, function () use ($output): void {
                $this->running = false;
                $output->writeln('');
            });
        }
    }
}
