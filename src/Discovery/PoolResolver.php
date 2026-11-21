<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Discovery;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Contracts\Pool;
use Pharaonic\Laravel\Modulator\DirectoryPool;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use Pharaonic\Laravel\Modulator\Support\Files;

/**
 * Turns `pharaonic.modulator.pools` and the pools that installed Composer
 * packages declare (`extra.pharaonic.modulator.pools`) into Pool objects.
 *
 * @internal
 */
final class PoolResolver
{
    /**
     * Default priority of a package pool: below the application's legacy pool (90).
     */
    public const PACKAGE_PRIORITY = 50;

    public function __construct(
        private Container $container,
        private string $vendorPath
    ) {}

    /**
     * @param  mixed  $config  The `pharaonic.modulator.pools` value.
     * @return list<Pool>
     *
     * @throws InvalidArgumentException
     */
    public function configured($config): array
    {
        if (! is_array($config)) {
            throw new InvalidArgumentException('[pharaonic.modulator.pools] must be an array of pools.');
        }

        $pools = [];

        foreach ($config as $name => $pool) {
            $name = (string) $name;

            if (is_string($pool)) {
                $instance = $this->container->make($pool);

                if (! $instance instanceof Pool) {
                    throw new InvalidArgumentException(sprintf('Pool [%s] class [%s] must implement %s.', $name, $pool, Pool::class));
                }

                $pools[] = $instance;

                continue;
            }

            if (! is_array($pool) || ! isset($pool['path']) || ! is_string($pool['path']) || $pool['path'] === '') {
                throw new InvalidArgumentException(sprintf('Pool [%s] needs a "path" string, or a class name implementing %s.', $name, Pool::class));
            }

            $namespace = $pool['namespace'] ?? 'Modules';
            $priority = $pool['priority'] ?? 0;

            if (! is_string($namespace) || ! $this->isValidNamespace($namespace)) {
                throw new InvalidArgumentException(sprintf('Pool [%s] has an invalid "namespace".', $name));
            }

            if (! is_int($priority)) {
                throw new InvalidArgumentException(sprintf('Pool [%s] "priority" must be an integer.', $name));
            }

            $pools[] = new DirectoryPool($name, $pool['path'], $namespace, $priority, null, (bool) ($pool['legacy'] ?? false));
        }

        return $pools;
    }

    /**
     * The file whose change means installed packages (and their pools) changed.
     */
    public function installedFile(): string
    {
        return $this->vendorPath.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.json';
    }

    /**
     * @return array{pools: list<Pool>, diagnostics: list<Diagnostic>}
     */
    public function packages(): array
    {
        $file = $this->installedFile();
        $pools = [];
        $diagnostics = [];

        if (! is_file($file)) {
            return ['pools' => [], 'diagnostics' => []];
        }

        $installed = json_decode((string) file_get_contents($file), true);
        $packages = is_array($installed) && isset($installed['packages']) ? $installed['packages'] : $installed;

        if (! is_array($packages)) {
            return ['pools' => [], 'diagnostics' => [Diagnostic::warning(sprintf('Cannot read [%s].', $file))]];
        }

        foreach ($packages as $package) {
            if (! is_array($package) || ! is_string($package['name'] ?? null)) {
                continue;
            }

            $extra = $package['extra'] ?? null;
            $declared = is_array($extra) && is_array($extra['pharaonic'] ?? null) && is_array($extra['pharaonic']['modulator'] ?? null)
                ? ($extra['pharaonic']['modulator']['pools'] ?? null)
                : null;

            if ($declared === null) {
                continue;
            }

            $name = $package['name'];
            $root = $this->installPath($package, $name);

            if (! is_array($declared) || $root === null) {
                $diagnostics[] = Diagnostic::error(sprintf('Package [%s] declares invalid modulator pools.', $name));

                continue;
            }

            if (isset($declared['path'])) {
                $declared = [$declared];
            }

            foreach (array_values($declared) as $index => $pool) {
                $poolName = $index === 0 ? $name : $name.':'.($index + 1);
                $path = is_array($pool) ? ($pool['path'] ?? null) : null;
                $namespace = is_array($pool) ? ($pool['namespace'] ?? 'Modules') : null;
                $priority = is_array($pool) ? ($pool['priority'] ?? self::PACKAGE_PRIORITY) : null;

                if (! is_string($path) || ! is_string($namespace) || ! is_int($priority) || ! $this->isValidNamespace($namespace)) {
                    $diagnostics[] = Diagnostic::error(sprintf('Package [%s] pool #%d needs a relative "path", an optional "namespace" and an optional integer "priority".', $name, $index + 1));

                    continue;
                }

                $directory = realpath($root.DIRECTORY_SEPARATOR.$path);

                if ($directory === false || ! Files::within($directory, $root)) {
                    $diagnostics[] = Diagnostic::error(sprintf('Package [%s] pool path [%s] does not exist inside the package.', $name, $path));

                    continue;
                }

                $pools[] = new DirectoryPool($poolName, $directory, $namespace, $priority, $name);
            }
        }

        usort($pools, static function (Pool $a, Pool $b): int {
            return strcmp($a->name(), $b->name());
        });

        return ['pools' => $pools, 'diagnostics' => $diagnostics];
    }

    /**
     * @param  array<mixed>  $package
     */
    private function installPath(array $package, string $name): ?string
    {
        $relative = $package['install-path'] ?? null;
        $path = is_string($relative)
            ? $this->vendorPath.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.$relative
            : $this->vendorPath.DIRECTORY_SEPARATOR.$name;

        $real = realpath($path);

        return $real === false ? null : $real;
    }

    private function isValidNamespace(string $namespace): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', trim($namespace, '\\')) === 1;
    }
}
