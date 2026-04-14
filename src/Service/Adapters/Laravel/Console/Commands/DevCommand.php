<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DevCommand extends Command
{
    protected $signature = 'zolta:dev
                            {--composer= : Composer binary to use (defaults to COMPOSER_BINARY or composer)}
                            {--no-watch : Skip starting the route watcher}';

    protected $description = 'Run the application dev stack and Zolta route watcher together.';

    public function handle(): int
    {
        $this->taggedInfo('zolta', 'Starting dev stack (composer dev + route watcher)...');

        if (! $this->attributeCacheExists()) {
            $this->taggedInfo('zolta', 'Priming attribute route cache (scanning controllers)...');
            $primeResult = $this->runBlockingProcess(
                $this->artisanCommand(['zolta:routes:cache', '--ansi']),
                'routes'
            );

            if ($primeResult['ok']) {
                $this->line(sprintf('[routes] attribute route cache refreshed in %0.1fs', $primeResult['duration']));
            } else {
                $this->warn(sprintf('[routes] attribute route cache failed after %0.1fs; continuing without warmup', $primeResult['duration']));
            }
        } else {
            $this->taggedInfo('zolta', 'Attribute route cache already present; skipping warmup.');
        }

        try {
            $devProcess = $this->startProcess($this->composerCommand(), base_path());
        } catch (\Throwable $e) {
            $this->error("Unable to start composer dev: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $watchProcess = null;
        if (! $this->option('no-watch')) {
            try {
                $watchProcess = $this->startProcess(
                    [PHP_BINARY, base_path('artisan'), 'zolta:routes:watch', '--ansi'],
                    base_path(),
                    enableTty: false
                );
            } catch (\Throwable $e) {
                $this->error("Unable to start route watcher: {$e->getMessage()}");
                $devProcess->stop(0);

                return Command::FAILURE;
            }
        }

        while ($devProcess->isRunning() || ($watchProcess?->isRunning() ?? false)) {
            $this->drainOutput($devProcess, 'dev');
            if ($watchProcess instanceof Process) {
                $this->drainOutput($watchProcess, 'watch');
            }

            if (! $devProcess->isRunning() && $watchProcess?->isRunning()) {
                $watchProcess->stop(0);
            }

            usleep(50000);
        }

        // Final drain to avoid missing trailing output
        $this->drainOutput($devProcess, 'dev');
        if ($watchProcess instanceof Process) {
            $this->drainOutput($watchProcess, 'watch');
        }

        $devExit = $devProcess->getExitCode() ?? 0;
        $watchExit = $watchProcess?->getExitCode() ?? 0;

        if ($devExit !== 0) {
            $this->error("Composer dev exited with code {$devExit}.");
        }
        if ($watchProcess && $watchExit !== 0) {
            $this->error("Route watcher exited with code {$watchExit}.");
        }

        return ($devExit === 0 && $watchExit === 0) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function composerCommand(): array
    {
        $custom = $this->option('composer') ?: getenv('COMPOSER_BINARY');
        if (is_string($custom) && $custom !== '') {
            return [$custom, 'run', 'dev', '--', '--ansi'];
        }

        if (file_exists(base_path('composer.phar'))) {
            return [PHP_BINARY, base_path('composer.phar'), 'run', 'dev', '--', '--ansi'];
        }

        return ['composer', 'run', 'dev', '--', '--ansi'];
    }

    /**
     * @param  list<string>  $args
     * @return list<string>
     */
    private function artisanCommand(array $args): array
    {
        return array_merge([PHP_BINARY, base_path('artisan')], $args);
    }

    /**
     * @param  list<string>  $command
     */
    private function startProcess(array $command, string $cwd, bool $enableTty = true): Process
    {
        $process = new Process($command, $cwd, $this->colorEnv());
        $process->setTimeout(null);

        if ($enableTty && function_exists('posix_isatty') && posix_isatty(STDOUT)) {
            try {
                $process->setTty(true);
            } catch (\Throwable) {
                // fall back silently if tty is unavailable
            }
        }

        $process->start();

        return $process;
    }

    /**
     * @param  list<string>  $command
     * @return array{ok: bool, duration: float}
     */
    private function runBlockingProcess(array $command, string $label): array
    {
        $started = microtime(true);

        $process = new Process($command, base_path(), $this->colorEnv());
        $process->setTimeout(null);

        if (function_exists('posix_isatty') && posix_isatty(STDOUT)) {
            try {
                $process->setTty(true);
            } catch (\Throwable) {
            }
        }

        $process->start();

        $spinnerFrames = ['-', '\\', '|', '/'];
        $spinnerIndex = 0;
        $lastSpinner = microtime(true);
        $useSpinner = $this->supportsSpinner();

        while ($process->isRunning()) {
            $this->drainOutput($process, $label);

            if ($useSpinner && (microtime(true) - $lastSpinner) >= 0.1) {
                $message = sprintf('[%s] %s Priming attribute routes...', $label, $spinnerFrames[$spinnerIndex]);
                $this->output->write("\r".$message);
                $spinnerIndex = ($spinnerIndex + 1) % count($spinnerFrames);
                $lastSpinner = microtime(true);
            }

            usleep(50_000);
        }

        // Final drain and clear spinner line
        $this->drainOutput($process, $label);
        if ($useSpinner) {
            $this->output->write("\r".str_repeat(' ', 120)."\r");
        }

        return [
            'ok' => $process->isSuccessful(),
            'duration' => microtime(true) - $started,
        ];
    }

    private function drainOutput(Process $process, string $label): void
    {
        $out = $process->getIncrementalOutput();
        if ($out !== '') {
            $this->output->writeln($this->prefix($label, $out));
        }

        $err = $process->getIncrementalErrorOutput();
        if ($err !== '') {
            $this->output->writeln($this->prefix($label, $err));
        }
    }

    private function prefix(string $label, string $buffer): string
    {
        $lines = preg_split('/\\r?\\n/', rtrim($buffer, '\\r\\n'));
        if ($lines === false) {
            return $buffer;
        }

        return implode(PHP_EOL, array_map(
            fn (string $line): string => sprintf('%s %s', $this->formatLabel($label), $line),
            $lines
        ));
    }

    /**
     * @return array<string,string>
     */
    private function colorEnv(): array
    {
        return array_merge($_SERVER, $_ENV, [
            'FORCE_COLOR' => '1',
            'TERM' => getenv('TERM') ?: 'xterm-256color',
        ]);
    }

    private function supportsSpinner(): bool
    {
        if (! $this->output->isDecorated()) {
            return false;
        }

        if (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }

        return true;
    }

    private function taggedInfo(string $tag, string $message): void
    {
        $this->info("[{$tag}] {$message}");
    }

    private function formatLabel(string $label): string
    {
        $text = "[{$label}]";

        if (! $this->output->isDecorated()) {
            return $text;
        }

        $color = match ($label) {
            'watch' => "\e[33m", // yellow
            default => null,
        };

        return $color ? $color.$text."\e[0m" : $text;
    }

    private function attributeCacheExists(): bool
    {
        return is_file(base_path('bootstrap/cache/attribute_routes.php'))
            && is_file(base_path('bootstrap/cache/attribute_routes_manifest.php'));
    }
}
