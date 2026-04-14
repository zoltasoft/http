<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zolta\Http\Symfony\Bootstrap\Routing\SymfonyRouteLoader;

final class CacheRoutesCommand extends Command
{
    public function __construct(
        private readonly SymfonyRouteLoader $symfonyRouteLoader,
        private readonly ContainerInterface $container
    ) {
        parent::__construct('zolta:routes:cache');
    }

    protected function configure(): void
    {
        $this->setDescription('Scan attribute routes and warm the route collection.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = (array) $this->container->getParameter('zolta.routes.paths');

        try {
            $collection = $this->symfonyRouteLoader->buildCollection($paths);
            $output->writeln(sprintf('✅ Discovered %d routes.', count($collection)));
        } catch (\Throwable $e) {
            $output->writeln('<error>❌ Failed to cache routes: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
