<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use RuntimeException;

/**
 * The module selected with module:use, kept per operating-system user under
 * storage/framework/modulator/context, so developers sharing a checkout do
 * not overwrite each other's selection.
 *
 * It is a developer convenience: commands read it only on an interactive
 * console outside CI and production (see isTrusted()).
 *
 * @internal
 */
final class Context
{
    public function __construct(private string $storage) {}

    public function get(): ?string
    {
        $file = $this->file();

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);
        $module = is_array($data) ? ($data['module'] ?? null) : null;

        return is_string($module) ? $module : null;
    }

    /**
     * @throws RuntimeException
     */
    public function set(string $id): void
    {
        $file = $this->file(true);
        Files::put($file, (string) json_encode(['module' => $id], JSON_UNESCAPED_SLASHES).PHP_EOL);
        @chmod($file, 0600);
    }

    public function clear(): bool
    {
        $file = $this->file();

        return is_file($file) && @unlink($file);
    }

    /**
     * Whether the stored selection may be used: an interactive console,
     * outside CI and production.
     */
    public static function isTrusted(bool $interactive, bool $production): bool
    {
        $ci = getenv('CI');

        return $interactive && ! $production && ($ci === false || $ci === '' || $ci === '0' || strtolower($ci) === 'false');
    }

    private function file(bool $create = false): string
    {
        $directory = $create
            ? Files::workspace($this->storage, 'context')
            : $this->storage.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'modulator'.DIRECTORY_SEPARATOR.'context';

        return $directory.DIRECTORY_SEPARATOR.$this->user().'.json';
    }

    private function user(): string
    {
        if (function_exists('posix_geteuid')) {
            return 'uid-'.posix_geteuid();
        }

        $name = getenv('USERNAME') ?: (getenv('USER') ?: get_current_user());

        return 'user-'.substr(sha1((string) $name), 0, 16);
    }
}
