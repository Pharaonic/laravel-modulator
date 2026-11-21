<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Autoload;

use Composer\Autoload\ClassLoader;
use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;

/**
 * Registers module classes with the application's Composer class loader.
 *
 * Each enabled module gets its class map (built at discovery, so it also works
 * with optimized and authoritative Composer class maps) and its PSR-4 prefixes
 * (prepended, so new files work in development without a rebuild). The module
 * class map is merged last, so it wins over Composer entries for the same class.
 *
 * @internal
 */
final class Autoloader
{
    public function __construct(
        private string $vendorPath
    ) {
    }

    /**
     * The Composer loader of the application's vendor directory, or the first registered one.
     */
    public function loader(): ?ClassLoader
    {
        $loaders = ClassLoader::getRegisteredLoaders();
        $vendor = realpath($this->vendorPath);

        foreach ($loaders as $path => $loader) {
            if ($vendor !== false && realpath($path) === $vendor) {
                return $loader;
            }
        }

        foreach (spl_autoload_functions() as $function) {
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, string>>  $classMaps  Class map per module identity.
     */
    public function register(ModuleRegistry $registry, array $classMaps): void
    {
        $loader = $this->loader();

        if ($loader === null) {
            return;
        }

        foreach ($registry->enabled() as $id => $module) {
            $source = $module->source();

            if ($source->legacy) {
                continue;
            }

            if (isset($classMaps[$id]) && $classMaps[$id] !== []) {
                $loader->addClassMap($classMaps[$id]);
            }

            foreach ($source->prefixes() as $prefix => $directory) {
                $loader->addPsr4($prefix, $directory, true);
            }
        }
    }

    /**
     * Composer mappings that claim a class or namespace of a selected module
     * but point outside its source, read from the generated Composer files so
     * the result reflects the last `composer dump-autoload`.
     *
     * @param  list<Source>  $selected
     * @param  list<Source>  $shadowed
     * @return list<Diagnostic>
     */
    public function conflicts(array $selected, array $shadowed): array
    {
        $namespaces = [];

        foreach ($selected as $source) {
            if (! $source->legacy) {
                $namespaces[$source->namespace] = $source;
            }
        }

        if ($namespaces === []) {
            return [];
        }

        $diagnostics = [];
        $composer = $this->vendorPath.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR;
        $shadowedRoots = array_map(static function (Source $source): string {
            return $source->path;
        }, $shadowed);

        foreach ($this->readMap($composer.'autoload_classmap.php') as $class => $file) {
            $owner = $this->owner($class, $namespaces);

            if ($owner === null || ! is_string($file) || $this->isInside($file, $owner->path)) {
                continue;
            }

            $diagnostics[] = Diagnostic::error(sprintf(
                'Composer maps [%s] to [%s], outside the selected source of module [%s] (%s). Remove that mapping from the composer.json that declares it (a package must not autoload its modules itself), then run "composer dump-autoload" and "php artisan module:cache".',
                $class,
                $file,
                $owner->id,
                $owner->path
            ), $owner->id);
        }

        foreach ($this->readMap($composer.'autoload_psr4.php') as $prefix => $directories) {
            if (! is_string($prefix) || ! is_array($directories)) {
                continue;
            }

            foreach ($namespaces as $namespace => $source) {
                $root = $namespace.'\\';

                foreach ($directories as $directory) {
                    if (! is_string($directory)) {
                        continue;
                    }

                    if (str_starts_with($prefix, $root)) {
                        if (! $this->isInside($directory, $source->path)) {
                            $diagnostics[] = Diagnostic::error(sprintf(
                                'Composer maps the namespace [%s] to [%s], outside the selected source of module [%s] (%s). Remove that PSR-4 entry, then run "composer dump-autoload" and "php artisan module:cache".',
                                $prefix,
                                $directory,
                                $source->id,
                                $source->path
                            ), $source->id);
                        }

                        continue;
                    }

                    if (! str_starts_with($root, $prefix)) {
                        continue;
                    }

                    $candidate = realpath($directory.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($prefix))));

                    if ($candidate === false || $this->isInside($candidate, $source->path) || in_array($candidate, $shadowedRoots, true)) {
                        continue;
                    }

                    $diagnostics[] = Diagnostic::warning(sprintf(
                        'Composer maps [%s] to [%s], so classes of module [%s] could also load from [%s]. Keep module classes out of that directory.',
                        $prefix,
                        $directory,
                        $source->id,
                        $candidate
                    ), $source->id);
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, Source>  $namespaces
     */
    private function owner(string $class, array $namespaces): ?Source
    {
        $namespace = $class;

        while (($position = strrpos($namespace, '\\')) !== false) {
            $namespace = substr($namespace, 0, $position);

            if (isset($namespaces[$namespace])) {
                return $namespaces[$namespace];
            }
        }

        return null;
    }

    private function isInside(string $path, string $directory): bool
    {
        $real = realpath($path);
        $path = $real === false ? $path : $real;

        return $path === $directory || str_starts_with($path, $directory.DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<mixed>
     */
    private function readMap(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $map = (static function (string $file) {
            return require $file;
        })($file);

        return is_array($map) ? $map : [];
    }
}
