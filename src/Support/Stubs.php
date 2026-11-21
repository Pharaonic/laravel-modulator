<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use RuntimeException;

/**
 * Finds and renders generator stubs. A stub in the custom directory
 * (`pharaonic.modulator.generation.stubs`, or `stubs/modulator` when that is
 * null and the directory exists) replaces the package stub with the same name,
 * e.g. "controller.stub" or "module/provider.stub".
 *
 * @internal
 */
final class Stubs
{
    public function __construct(private ?string $custom = null)
    {
    }

    /**
     * @param  mixed  $custom  The `generation.stubs` value.
     * @param  string|null  $published  The directory `vendor:publish --tag=modulator-stubs` writes to, used when $custom is null.
     */
    public static function fromConfig($custom, ?string $published = null): self
    {
        if (is_string($custom) && $custom !== '') {
            return new self(rtrim($custom, '/\\'));
        }

        return new self($published !== null && is_dir($published) ? $published : null);
    }

    /**
     * The directory of the package stubs.
     */
    public static function packagePath(): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'stubs';
    }

    public function path(string $name): string
    {
        if ($this->custom !== null && is_file($file = $this->custom.DIRECTORY_SEPARATOR.$name)) {
            return $file;
        }

        return self::packagePath().DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @param  array<string, string>  $replace  Token (without braces) => value.
     *
     * @throws RuntimeException
     */
    public function render(string $name, array $replace): string
    {
        $file = $this->path($name);
        $stub = @file_get_contents($file);

        if ($stub === false) {
            throw new RuntimeException(sprintf('The stub [%s] does not exist.', $file));
        }

        $tokens = [];

        foreach ($replace as $token => $value) {
            $tokens['{{ '.$token.' }}'] = $value;
            $tokens['{{'.$token.'}}'] = $value;
        }

        return strtr($stub, $tokens);
    }
}
