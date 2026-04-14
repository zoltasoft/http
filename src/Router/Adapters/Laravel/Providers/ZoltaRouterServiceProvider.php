<?php

declare(strict_types=1);

namespace Zolta\Http\Router\Laravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Package entry provider that wires router attribute/service/command providers.
 *
 * Route configuration is read from the `zolta-http.routes` config key,
 * published by ZoltaHttpServiceProvider.
 */
class ZoltaRouterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(ZoltaRouterAttributeServiceProvider::class);
        $this->app->register(ZoltaRouterServiceServiceProvider::class);
        $this->app->register(ZoltaRouterCommandServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->shouldSkipAttributeRoutes()) {
            return;
        }

        if (file_exists($file = base_path('bootstrap/cache/attribute_routes.php'))) {
            require $file;
        }
    }

    private function shouldSkipAttributeRoutes(): bool
    {
        if (! $this->app->runningInConsole()) {
            return false;
        }

        $command = $this->resolveConsoleCommand();
        if ($command === null) {
            return false;
        }

        $configured = (array) config('zolta-http.routes.cache.skip_commands', []);
        $configured[] = 'make:zolta-update-namespace';
        $configured[] = 'package:discover';

        foreach (array_unique($configured) as $pattern) {
            if ($pattern !== '' && ($pattern === $command || Str::is($pattern, $command))) {
                return true;
            }
        }

        return false;
    }

    private function resolveConsoleCommand(): ?string
    {
        $argv = $_SERVER['argv'] ?? null;

        if (! is_array($argv) || $argv === []) {
            return null;
        }

        foreach ($argv as $index => $value) {
            if (basename((string) $value) === 'artisan') {
                return $this->normaliseConsoleCommand($argv[$index + 1] ?? null);
            }
        }

        return $this->normaliseConsoleCommand($argv[1] ?? null);
    }

    private function normaliseConsoleCommand(?string $command): ?string
    {
        return ($command && ! str_starts_with($command, '-'))
            ? $command
            : null;
    }
}
