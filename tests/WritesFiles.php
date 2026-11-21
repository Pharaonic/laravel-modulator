<?php

namespace Pharaonic\Laravel\Modulator\Tests;

trait WritesFiles
{
    public static function temporaryDirectory(string $prefix): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);

        return (string) realpath($directory);
    }

    /**
     * Write a file relative to the base directory (absolute paths are kept).
     */
    protected function write(string $path, string $contents = ''): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->isAbsolute($path) ? $path : $this->base.'/'.$path);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    protected function directory(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->isAbsolute($path) ? $path : $this->base.'/'.$path);

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    private function isAbsolute(string $path): bool
    {
        return $path !== '' && ($path[0] === '/' || $path[0] === '\\' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1);
    }
}
