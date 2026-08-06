<?php

declare(strict_types=1);

namespace Zolta\Http\Identity\Laravel;

use Illuminate\Foundation\Application;
use Zolta\Framework\FrameworkAdapterInterface;

final class LaravelIdentityAdapter implements FrameworkAdapterInterface
{
    public static function supports(): bool
    {
        return class_exists(Application::class)
            && function_exists('app');
    }

    public static function priority(): int
    {
        return 90;
    }

    /**
     * @return array<class-string, class-string>
     */
    public static function bindings(): array
    {
        return [];
    }
}
