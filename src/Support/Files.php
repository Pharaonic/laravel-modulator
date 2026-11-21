<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/**
 * Safe file writes shared by the commands that change the project.
 *
 * @internal
 */
final class Files
{
    /**
     * Write a file atomically: a temporary file in the same directory is
     * renamed over the target, so readers see the old or the new content,
     * never a partial one.
     *
     * Without $overwrite the file must not exist yet; two processes creating
     * the same file cannot both succeed.
     *
     * @throws RuntimeException
     */
    public static function put(string $path, string $contents, bool $overwrite = true): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create the directory [%s].', $directory));
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(sprintf('The directory [%s] is not writable.', $directory));
        }

        $temporary = $directory.DIRECTORY_SEPARATOR.'.'.basename($path).'.'.bin2hex(random_bytes(6)).'.tmp';

        if (@file_put_contents($temporary, $contents) !== strlen($contents)) {
            @unlink($temporary);

            throw new RuntimeException(sprintf('Cannot write [%s].', $path));
        }

        if (is_file($path)) {
            $permissions = @fileperms($path);

            if ($permissions !== false) {
                @chmod($temporary, $permissions & 0777);
            }
        }

        if ($overwrite) {
            $moved = @rename($temporary, $path);
        } else {
            // link() fails when the target exists, atomically.
            $moved = @link($temporary, $path);
            @unlink($temporary);

            if (! $moved && ! file_exists($path) && ($handle = @fopen($path, 'x')) !== false) {
                $moved = fwrite($handle, $contents) === strlen($contents);
                fclose($handle);
            }
        }

        if (! $moved) {
            @unlink($temporary);

            throw new RuntimeException(file_exists($path) && ! $overwrite
                ? sprintf('[%s] already exists.', $path)
                : sprintf('Cannot write [%s].', $path));
        }
    }

    /**
     * Move a directory: a rename, or a copy then a delete across filesystems.
     *
     * @throws RuntimeException
     */
    public static function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }

        $files = new Filesystem;

        if (! $files->copyDirectory($from, $to)) {
            throw new RuntimeException(sprintf('Cannot move [%s] to [%s].', $from, $to));
        }

        self::deleteDirectory($from);
    }

    /**
     * Delete a directory and its contents without following links: a symlink
     * or a Windows junction inside it (or the directory itself, when it is one)
     * is removed as a link, so nothing outside the directory is deleted.
     * Laravel's deleteDirectory() follows junctions on Windows.
     */
    public static function deleteDirectory(string $directory): void
    {
        if (self::isLink($directory)) {
            self::removeLink($directory);

            return;
        }

        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if (self::isLink($path)) {
                self::removeLink($path);
            } elseif (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * Whether $path is a symlink or a junction: a directory that resolves
     * somewhere else than its own place in its parent.
     */
    private static function isLink(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }

        if (! is_dir($path)) {
            return false;
        }

        $real = realpath($path);
        $parent = realpath(dirname($path));

        return $real !== false && $parent !== false
            && self::comparable($real) !== self::comparable($parent.DIRECTORY_SEPARATOR.basename($path));
    }

    private static function removeLink(string $path): void
    {
        // A link to a directory is removed with rmdir() on Windows, unlink() elsewhere.
        if (PHP_OS_FAMILY === 'Windows' && is_dir($path)) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    /**
     * Whether $path is $directory or lies inside it, after resolving symlinks.
     */
    public static function isInside(string $path, string $directory): bool
    {
        $path = realpath($path);
        $directory = realpath($directory);

        if ($path === false || $directory === false) {
            return false;
        }

        return self::within($path, $directory);
    }

    /**
     * Whether $path is $directory or lies inside it, comparing the strings only:
     * both separators are accepted, and Windows paths compare case-insensitively.
     */
    public static function within(string $path, string $directory): bool
    {
        $path = self::comparable($path);
        $directory = self::comparable($directory);

        return $path === $directory || str_starts_with($path, $directory.DIRECTORY_SEPARATOR);
    }

    /**
     * The path with the platform separator and no trailing separator.
     */
    public static function normalize(string $path): string
    {
        $normalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $normalized === '' ? $path : $normalized;
    }

    /**
     * $path relative to $base with "/" separators, for output; $path itself
     * (normalized) when it is outside $base.
     */
    public static function relative(string $path, string $base): string
    {
        $normalized = self::normalize($path);
        $root = self::normalize($base);

        if (! self::within($normalized, $root) || self::comparable($normalized) === self::comparable($root)) {
            return $normalized;
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', substr($normalized, strlen($root) + 1));
    }

    private static function comparable(string $path): string
    {
        $path = self::normalize($path);

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    /**
     * Create Modulator's private directory under storage/framework, kept out of
     * version control, and return its path.
     *
     * @throws RuntimeException
     */
    public static function workspace(string $storage, string $name = ''): string
    {
        $root = $storage.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'modulator';

        if (! is_dir($root) && ! @mkdir($root, 0755, true) && ! is_dir($root)) {
            throw new RuntimeException(sprintf('Cannot create the directory [%s]. Make storage/framework writable.', $root));
        }

        if (! is_file($root.DIRECTORY_SEPARATOR.'.gitignore')) {
            @file_put_contents($root.DIRECTORY_SEPARATOR.'.gitignore', '*'.PHP_EOL.'!.gitignore'.PHP_EOL);
        }

        if ($name === '') {
            return $root;
        }

        $directory = $root.DIRECTORY_SEPARATOR.$name;

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create the directory [%s].', $directory));
        }

        return $directory;
    }

    /**
     * Run $callback while holding an exclusive lock named $name.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws RuntimeException
     */
    public static function locked(string $storage, string $name, callable $callback)
    {
        $file = self::workspace($storage).DIRECTORY_SEPARATOR.$name.'.lock';
        $handle = @fopen($file, 'c');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot open the lock file [%s].', $file));
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException(sprintf('Cannot lock [%s].', $file));
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
