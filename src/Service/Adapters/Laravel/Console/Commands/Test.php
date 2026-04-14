<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'zolta:test')]

class Test extends Command
{
    protected $signature = 'zolta:test';

    protected $description = 'My first package command';

    public function handle(): int
    {
        $this->info('Test command executed successfully!');

        return Command::SUCCESS;
    }
}
