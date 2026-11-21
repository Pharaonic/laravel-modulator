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

        $files->deleteDirectory($from);
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

        return $path === $directory || str_starts_with($path, rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
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
