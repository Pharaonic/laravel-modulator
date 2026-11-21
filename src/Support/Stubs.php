<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use RuntimeException;

/**
 * Finds and renders generator stubs. A stub in the custom directory
 * (`pharaonic.modulator.generation.stubs`) replaces the package stub with the
 * same name, e.g. "controller.stub" or "module/provider.stub".
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
     */
    public static function fromConfig($custom): self
    {
        return new self(is_string($custom) && $custom !== '' ? rtrim($custom, '/\\') : null);
    }

    public function path(string $name): string
    {
        if ($this->custom !== null && is_file($file = $this->custom.DIRECTORY_SEPARATOR.$name)) {
            return $file;
        }

        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'stubs'.DIRECTORY_SEPARATOR.$name;
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
