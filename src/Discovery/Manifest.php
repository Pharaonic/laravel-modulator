<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Discovery;

use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Autoload\Autoloader;
use Pharaonic\Laravel\Modulator\Contracts\Pool;
use Pharaonic\Laravel\Modulator\DirectoryPool;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use RuntimeException;

/**
 * The discovery result: pools, sources, class maps and diagnostics, cached in
 * bootstrap/cache/modulator.php.
 *
 * Module state (`disabled`) is not part of it: the registry applies the
 * configuration at runtime, so enabling or disabling a module never needs a
 * rediscovery. The cache is rebuilt when the configured pools or the
 * installed Composer packages change.
 *
 * @internal
 *
 * @phpstan-import-type SourceData from Source
 *
 * @phpstan-type PoolData array{name: string, path: string, namespace: string, priority: int, package: string|null, legacy: bool}
 * @phpstan-type ManifestData array{version: int, signature: string, pools: list<PoolData>, sources: list<SourceData>, classmap: array<string, array<string, string>>, diagnostics: list<array{level: string, message: string, module: string|null}>, watch: array<string, int|null>}
 */
final class Manifest
{
    public const VERSION = 1;

    /**
     * @var ManifestData|null
     */
    private ?array $data = null;

    /**
     * @param  mixed  $pools  The `pharaonic.modulator.pools` value.
     */
    public function __construct(
        private Filesystem $files,
        private PoolResolver $resolver,
        private Autoloader $autoloader,
        private string $path,
        private $pools,
        private bool $cache
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The cached manifest when it is current, otherwise a fresh discovery
     * (written to the cache when caching is enabled and the directory is writable).
     *
     * @return ManifestData
     */
    public function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $configured = $this->resolver->configured($this->pools);
        $signature = $this->signature($configured);

        if ($this->cache) {
            $cached = $this->read();

            if ($cached !== null && $cached['signature'] === $signature && $this->isFresh($cached['watch'])) {
                return $this->data = $cached;
            }
        }

        $data = $this->discover($configured, $signature);

        if ($this->cache && $this->isWritable()) {
            $this->write($data);
        }

        return $this->data = $data;
    }

    /**
     * Discover again and write the cache, whatever the configuration says.
     *
     * @return ManifestData
     *
     * @throws RuntimeException When the cache directory is not writable.
     */
    public function rebuild(): array
    {
        $configured = $this->resolver->configured($this->pools);
        $data = $this->discover($configured, $this->signature($configured));

        if (! $this->isWritable()) {
            throw new RuntimeException(sprintf('The [%s] directory must be present and writable.', dirname($this->path)));
        }

        $this->write($data);

        return $this->data = $data;
    }

    /**
     * A discovery of the current files, without reading or writing the cache.
     *
     * @return ManifestData
     */
    public function fresh(): array
    {
        $configured = $this->resolver->configured($this->pools);

        return $this->discover($configured, $this->signature($configured));
    }

    /**
     * The cached manifest as written, or null when there is none.
     *
     * @return ManifestData|null
     */
    public function cached(): ?array
    {
        return $this->read();
    }

    /**
     * A discovered pool by name.
     *
     * @return PoolData|null
     */
    public function pool(string $name): ?array
    {
        foreach ($this->load()['pools'] as $pool) {
            if ($pool['name'] === $name) {
                return $pool;
            }
        }

        return null;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cache;
    }

    public function clear(): bool
    {
        $this->data = null;

        if (! is_file($this->path)) {
            return false;
        }

        $this->files->delete($this->path);
        $this->invalidateOpcache();

        return true;
    }

    /**
     * @param  list<Pool>  $configured
     * @return ManifestData
     */
    private function discover(array $configured, string $signature): array
    {
        $packages = $this->resolver->packages();
        $pools = array_merge($configured, $packages['pools']);
        $diagnostics = $packages['diagnostics'];

        $names = [];

        foreach ($pools as $pool) {
            if (isset($names[$pool->name()])) {
                $diagnostics[] = Diagnostic::error(sprintf('Two pools are named [%s]. Pool names must be unique.', $pool->name()));
            }

            $names[$pool->name()] = true;
        }

        $discovered = (new Discoverer)->discover($pools);
        $diagnostics = array_merge($diagnostics, $discovered['diagnostics']);

        // Select without state: class maps cover every selected source.
        $registry = new ModuleRegistry($discovered['sources']);
        $selected = [];
        $shadowed = [];

        foreach ($registry->all() as $module) {
            $selected[$module->id()] = $module->source();
        }

        foreach ($discovered['sources'] as $source) {
            if (! isset($selected[$source->id]) || $selected[$source->id] !== $source) {
                $shadowed[] = $source;
            }
        }

        $builder = new ClassMapBuilder;
        $classmap = [];

        foreach ($selected as $id => $source) {
            if ($source->legacy) {
                continue;
            }

            $exclude = [];

            foreach ($selected as $childId => $child) {
                if (str_starts_with($childId, $id.'/')) {
                    $exclude[] = $source->classPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, substr($childId, strlen($id) + 1));
                }
            }

            $built = $builder->build($source, $exclude);
            $classmap[$id] = $built['classes'];
            $diagnostics = array_merge($diagnostics, $built['diagnostics']);
        }

        $diagnostics = array_merge($diagnostics, $this->autoloader->conflicts(array_values($selected), $shadowed));

        return [
            'version' => self::VERSION,
            'signature' => $signature,
            'pools' => array_map(static function (Pool $pool): array {
                return [
                    'name' => $pool->name(),
                    'path' => $pool->path(),
                    'namespace' => $pool->namespace(),
                    'priority' => $pool->priority(),
                    'package' => $pool->package(),
                    'legacy' => $pool instanceof DirectoryPool && $pool->isLegacy(),
                ];
            }, $pools),
            'sources' => array_map(static function (Source $source): array {
                return $source->toArray();
            }, $discovered['sources']),
            'classmap' => $classmap,
            'diagnostics' => array_map(static function (Diagnostic $diagnostic): array {
                return $diagnostic->toArray();
            }, $diagnostics),
            'watch' => $this->stamps($discovered['watch']),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, int|null>
     */
    private function stamps(array $paths): array
    {
        clearstatcache();
        $stamps = [];

        foreach ($paths as $path) {
            $time = @filemtime($path);
            $stamps[$path] = $time === false ? null : $time;
        }

        return $stamps;
    }

    /**
     * Whether no watched directory (pool roots, groups, module roots, module.php) changed.
     *
     * @param  array<string, int|null>  $watch
     */
    private function isFresh(array $watch): bool
    {
        return $this->stamps(array_keys($watch)) === $watch;
    }

    /**
     * @param  list<Pool>  $configured
     */
    private function signature(array $configured): string
    {
        $installed = $this->resolver->installedFile();

        return sha1((string) json_encode([
            self::VERSION,
            array_map(static function (Pool $pool): array {
                return [$pool->name(), $pool->path(), $pool->namespace(), $pool->priority(), $pool->package(), $pool instanceof DirectoryPool && $pool->isLegacy()];
            }, $configured),
            is_file($installed) ? [filemtime($installed), filesize($installed)] : null,
        ]));
    }

    /**
     * @return ManifestData|null
     */
    private function read(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        $data = (static function (string $file) {
            return @include $file;
        })($this->path);

        if (! is_array($data) || ($data['version'] ?? null) !== self::VERSION || ! is_string($data['signature'] ?? null)) {
            return null;
        }

        /** @var ManifestData $data */
        return $data;
    }

    /**
     * @param  ManifestData  $data
     */
    private function write(array $data): void
    {
        $this->files->replace($this->path, '<?php'.PHP_EOL.PHP_EOL.'return '.var_export($data, true).';'.PHP_EOL);
        $this->invalidateOpcache();
    }

    private function isWritable(): bool
    {
        $directory = dirname($this->path);

        return is_dir($directory) && is_writable($directory);
    }

    private function invalidateOpcache(): void
    {
        if (function_exists('opcache_invalidate') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            @opcache_invalidate($this->path, true);
        }
    }
}
