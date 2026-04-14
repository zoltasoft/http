<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Discovery;

use Symfony\Component\Finder\Finder;

final class MapScanner
{
    /**
     * @param  array<int,string>  $directories
     * @return array<int,string>
     */
    public static function files(array $directories): array
    {
        $files = [];
        $finder = new Finder;
        $finder->files()->in($directories)->name('*.php');

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            if ($realPath !== false) {
                $files[] = $realPath;
            }
        }

        return $files;
    }

    /**
     * @param  array<int,string>  $directories
     * @return array<int,class-string>
     */
    public static function classes(array $directories): array
    {
        $classes = [];
        foreach (self::files($directories) as $file) {
            $fqcn = self::parseClassFromFile($file);
            if ($fqcn === null) {
                continue;
            }

            if (! class_exists($fqcn, false)) {
                require_once $file;
            }

            if (class_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    public static function parseClassFromFile(string $filePath): ?string
    {
        $source = @file_get_contents($filePath);
        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = null;
        $class = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                $i++;
                while ($i < $count) {
                    $part = $tokens[$i];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (is_array($part) && in_array($part[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $namespace .= $part[1];
                    }
                    $i++;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_CLASS && self::isNamedClass($tokens, $i)) {
                $i++;
                while ($i < $count) {
                    $nameToken = $tokens[$i];
                    if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                        $class = $nameToken[1];
                        break 2;
                    }
                    $i++;
                }
            }
        }

        if ($class === null) {
            return null;
        }

        return trim(($namespace ? $namespace.'\\' : '').$class, '\\');
    }

    /** @param array<int,mixed> $tokens */
    private static function isNamedClass(array $tokens, int $index): bool
    {
        for ($j = $index - 1; $j >= 0; $j--) {
            $token = $tokens[$j];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($token) && in_array($token[0], [T_NEW, T_DOUBLE_COLON], true)) {
                return false;
            }
            break;
        }

        return true;
    }
}
