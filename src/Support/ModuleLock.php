<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * The module.lock that module:publish writes into a published copy: the
 * package and version it came from, and the hash of every copied file. It is
 * committed with the copy, so module:doctor can tell, on every machine, which
 * files the package changed since the copy and which ones were edited locally.
 *
 * @internal
 *
 * @phpstan-type LockData array{module: string, package: string, version: string|null, reference: string|null, children: bool, files: array<string, string>}
 */
final class ModuleLock
{
    public const FILE = 'module.lock';

    /**
     * Relative file path (with "/") => SHA-1, sorted. Child modules
     * (Modules/) are left out unless $withChildren, and module.lock always is.
     *
     * @return array<string, string>
     */
    public static function hashes(string $root, bool $withChildren): array
    {
        $hashes = [];

        if (! is_dir($root)) {
            return $hashes;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

            if ($relative === self::FILE || (! $withChildren && str_starts_with($relative, 'Modules/'))) {
                continue;
            }

            $hashes[$relative] = (string) sha1_file($file->getPathname());
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * The installed version and reference of a Composer package, read from vendor/composer/installed.json.
     *
     * @return array{version: string|null, reference: string|null}
     */
    public static function installed(string $vendorPath, string $package): array
    {
        $file = $vendorPath.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json';
        $installed = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $packages = is_array($installed) ? ($installed['packages'] ?? $installed) : [];

        foreach (is_array($packages) ? $packages : [] as $entry) {
            if (! is_array($entry) || ($entry['name'] ?? null) !== $package) {
                continue;
            }

            $version = $entry['pretty_version'] ?? $entry['version'] ?? null;
            $dist = is_array($entry['dist'] ?? null) ? $entry['dist'] : [];
            $origin = is_array($entry['source'] ?? null) ? $entry['source'] : [];
            $reference = $dist['reference'] ?? $origin['reference'] ?? null;

            return [
                'version' => is_string($version) ? $version : null,
                'reference' => is_string($reference) && $reference !== '' ? $reference : null,
            ];
        }

        return ['version' => null, 'reference' => null];
    }

    /**
     * The lock file contents.
     *
     * @param  array<string, string>  $files
     */
    public static function encode(string $module, string $package, ?string $version, ?string $reference, bool $children, array $files): string
    {
        ksort($files, SORT_STRING);

        return json_encode([
            'module' => $module,
            'package' => $package,
            'version' => $version,
            'reference' => $reference,
            'children' => $children,
            'files' => (object) $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * The lock of a module directory, or null when it has none.
     *
     * @return LockData|null
     *
     * @throws RuntimeException When the file exists but is not a valid lock.
     */
    public static function read(string $directory): ?array
    {
        $file = $directory.DIRECTORY_SEPARATOR.self::FILE;

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        $files = is_array($data) ? ($data['files'] ?? null) : null;

        if (! is_array($data) || ! is_string($data['module'] ?? null) || ! is_string($data['package'] ?? null) || ! is_array($files)) {
            throw new RuntimeException(sprintf('[%s] is not a valid module lock. Publish the module again to rewrite it.', $file));
        }

        $hashes = [];

        foreach ($files as $path => $hash) {
            if (is_string($path) && is_string($hash)) {
                $hashes[$path] = $hash;
            }
        }

        return [
            'module' => $data['module'],
            'package' => $data['package'],
            'version' => is_string($data['version'] ?? null) ? $data['version'] : null,
            'reference' => is_string($data['reference'] ?? null) ? $data['reference'] : null,
            'children' => ($data['children'] ?? false) === true,
            'files' => $hashes,
        ];
    }

    /**
     * Compare a published copy, its lock, and the package copy today.
     *
     * @param  LockData  $lock
     * @param  array<string, string>  $local  Hashes of the published copy.
     * @param  array<string, string>  $package  Hashes of the package copy.
     * @return array{package: list<string>, local: list<string>, both: list<string>}
     *                                                                               Files the package changed since the copy, files edited locally, and files changed on both sides.
     */
    public static function compare(array $lock, array $local, array $package): array
    {
        $paths = array_unique(array_merge(array_keys($lock['files']), array_keys($local), array_keys($package)));
        sort($paths, SORT_STRING);
        $result = ['package' => [], 'local' => [], 'both' => []];

        foreach ($paths as $path) {
            $base = $lock['files'][$path] ?? null;
            $byPackage = ($package[$path] ?? null) !== $base;
            $byLocal = ($local[$path] ?? null) !== $base;

            if ($byPackage && $byLocal && ($package[$path] ?? null) !== ($local[$path] ?? null)) {
                $result['both'][] = $path;
            } elseif ($byPackage && ! $byLocal) {
                $result['package'][] = $path;
            } elseif ($byLocal && ! $byPackage) {
                $result['local'][] = $path;
            }
        }

        return $result;
    }
}
