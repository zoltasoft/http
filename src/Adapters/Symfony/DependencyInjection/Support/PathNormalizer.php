<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Support;

final class PathNormalizer
{
    /**
     * Expand globbed directory patterns into real paths and drop missing ones.
     *
     * @param  array<int|string,string>  $paths
     * @return array<int,string>
     */
    public static function normalize(array $paths): array
    {
        $resolved = [];

        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '') {
                continue;
            }

            if (strpbrk($path, '*?[') !== false) {
                $globbed = glob($path, GLOB_ONLYDIR);
                if ($globbed !== false) {
                    foreach ($globbed as $g) {
                        $real = realpath($g);
                        if ($real !== false && is_dir($real)) {
                            $resolved[] = $real;
                        }
                    }
                }

                continue;
            }

            $real = realpath($path) ?: $path;
            if (is_dir($real)) {
                $resolved[] = $real;
            }
        }

        if ($resolved === []) {
            return array_values(array_unique(array_map(static fn (string $value): string => (string) $value, $paths)));
        }

        return array_values(array_unique($resolved));
    }
}
