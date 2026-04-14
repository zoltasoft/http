<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Discovery;

/**
 * Simple factory for default maps when none are provided.
 */
final class MapDefaults
{
    /** @return array<string, array<int|string, string>> */
    public static function commandMap(): array
    {
        return [];
    }

    /** @return array<string, array<int|string, string>> */
    public static function queryMap(): array
    {
        return [];
    }

    /** @return array<string, array<int, class-string>> */
    public static function eventMap(): array
    {
        return [];
    }
}
