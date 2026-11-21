<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use LogicException;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\ModuleRegistry;

/**
 * The Vite entry points of modules, for one application build.
 *
 * module:vite lists them as the inputs of the application's vite.config.js
 * (through resources/vite/modulator.js), and @moduleVite renders the tags of
 * one module's entries. A path is relative to the application's base path,
 * with "/" separators, as Vite's manifest names it.
 *
 * @internal
 */
final class ViteEntries
{
    /**
     * The entries a module has by default (pharaonic.modulator.vite.entries).
     *
     * @return list<string>
     */
    public static function configured(Repository $config): array
    {
        $entries = $config->get('pharaonic.modulator.vite.entries', ['resources/css/app.css', 'resources/js/app.js']);

        return array_values(array_filter(is_array($entries) ? $entries : [], static function ($entry): bool {
            return is_string($entry) && $entry !== '';
        }));
    }

    /**
     * The configured entries that exist in the module.
     *
     * @param  list<string>  $entries
     * @return list<string>
     */
    public static function existing(Module $module, array $entries, string $basePath): array
    {
        $found = [];

        foreach ($entries as $entry) {
            if (is_file($module->path($entry))) {
                $found[] = self::relative($module->path($entry), $basePath);
            }
        }

        return $found;
    }

    /**
     * A module file as Vite names it: relative to the base path, with "/".
     */
    public static function relative(string $path, string $basePath): string
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    /**
     * The tags of a module's entries (@moduleVite): its configured entries
     * that exist, or the given ones, which Vite must know.
     *
     * @param  string|list<string>|null  $entries  Paths inside the module.
     *
     * @throws InvalidArgumentException When the module does not exist.
     */
    public static function tags(string $module, $entries = null): HtmlString
    {
        $app = \Illuminate\Container\Container::getInstance();
        $found = self::module($app, $module);
        $basePath = self::basePath($app);

        if ($entries === null) {
            $config = $app->make('config');
            $paths = $config instanceof Repository ? self::existing($found, self::configured($config), $basePath) : [];
        } else {
            $paths = array_map(static function (string $entry) use ($found, $basePath): string {
                return self::relative($found->path($entry), $basePath);
            }, (array) $entries);
        }

        if ($paths === []) {
            return new HtmlString('');
        }

        $vite = $app->make(Vite::class);

        if (! $vite instanceof Vite) {
            throw new LogicException('Laravel\'s Vite is not available.');
        }

        return $vite($paths);
    }

    private static function module(Container $app, string $id): Module
    {
        $registry = $app->make(ModuleRegistry::class);
        $module = $registry instanceof ModuleRegistry ? $registry->find($id) : null;

        if ($module === null) {
            throw new InvalidArgumentException(sprintf('Module [%s] does not exist.', $id));
        }

        return $module;
    }

    private static function basePath(Container $app): string
    {
        if (! method_exists($app, 'basePath')) {
            throw new LogicException('The application base path is not available.');
        }

        $path = $app->basePath();

        return is_string($path) ? $path : '';
    }
}
