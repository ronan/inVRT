<?php

namespace InVRT\Core\Service;

/** Small filesystem helpers used across the Runner. */
class Filesystem
{
    /** Ensure a directory exists, creating it (and parents) if needed. Throws on failure. */
    public static function ensureDir(string $path): void
    {
        if ($path === '' || is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create directory: $path");
        }
    }

    /** Ensure parent directory exists and write contents atomically. Throws on failure. */
    public static function writeFile(string $path, string $contents): void
    {
        self::ensureDir(dirname($path));
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Failed to write file: $path");
        }
    }
}
