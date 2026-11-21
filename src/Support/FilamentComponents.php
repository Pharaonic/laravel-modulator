<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Foundation\Application;
use Pharaonic\Laravel\Modulator\Module;
use ReflectionClass;

/**
 * Registers the Filament 2 resources, pages and widgets of a module, when
 * Filament is installed, the way a Filament plugin does: the classes when
 * the "filament" manager is resolved, and their Livewire components at boot.
 *
 * <Module>\Filament\Resources holds resources, <Module>\Filament\Pages pages
 * and <Module>\Filament\Widgets widgets. The classes come from the module
 * class map, so nothing is scanned.
 *
 * @internal
 */
final class FilamentComponents
{
    /**
     * Register the classes with Filament once its manager is resolved.
     *
     * @param  list<string>  $classes  The module classes (from the manifest class map).
     */
    public static function register(Application $app, Module $module, array $classes): void
    {
        if (! $app->bound('filament')) {
            return;
        }

        $callback = static function ($filament) use ($module, $classes): void {
            if (! is_object($filament)) {
                return;
            }

            $found = self::classes($module, $classes);

            foreach (['resources' => 'registerResources', 'pages' => 'registerPages', 'widgets' => 'registerWidgets'] as $kind => $method) {
                if ($found[$kind] !== [] && method_exists($filament, $method)) {
                    $filament->{$method}($found[$kind]);
                }
            }
        };

        if ($app->resolved('filament')) {
            $callback($app->make('filament'));

            return;
        }

        $app->afterResolving('filament', $callback);
    }

    /**
     * Register the Livewire components behind the classes: resource pages,
     * relation managers and widgets, standalone pages and widgets.
     *
     * @param  list<string>  $classes
     */
    public static function boot(Application $app, Module $module, array $classes): void
    {
        if (! $app->bound('filament') || ! $app->bound('livewire')) {
            return;
        }

        $livewire = $app->make('livewire');

        if (! is_object($livewire) || ! method_exists($livewire, 'component')) {
            return;
        }

        $found = self::classes($module, $classes);
        $components = array_merge($found['pages'], $found['widgets']);

        foreach ($found['resources'] as $resource) {
            foreach (self::call($resource, 'getPages') as $page) {
                if (is_array($page) && isset($page['class'])) {
                    $components[] = $page['class'];
                }
            }

            foreach (self::call($resource, 'getRelations') as $relation) {
                $components = array_merge($components, is_object($relation) && method_exists($relation, 'getManagers') ? (array) $relation->getManagers() : [$relation]);
            }

            foreach (self::call($resource, 'getWidgets') as $widget) {
                $components[] = $widget;
            }
        }

        foreach (array_unique(array_filter($components, 'is_string')) as $component) {
            $getName = [$component, 'getName'];
            $name = is_callable($getName) ? call_user_func($getName) : null;

            if (is_string($name)) {
                $livewire->component($name, $component);
            }
        }
    }

    /**
     * The array a static method of a Filament class returns, without
     * referencing Filament itself (it is not a dependency).
     *
     * @return array<mixed>
     */
    private static function call(string $class, string $method): array
    {
        $callback = [$class, $method];
        $result = is_callable($callback) ? call_user_func($callback) : [];

        return is_array($result) ? $result : [];
    }

    /**
     * The concrete resource, page and widget classes of the module.
     *
     * @param  list<string>  $classes
     * @return array{resources: list<string>, pages: list<string>, widgets: list<string>}
     */
    private static function classes(Module $module, array $classes): array
    {
        return [
            'resources' => self::ofKind($module, $classes, 'Resources', 'Filament\\Resources\\Resource'),
            'pages' => self::ofKind($module, $classes, 'Pages', 'Filament\\Pages\\Page'),
            'widgets' => self::ofKind($module, $classes, 'Widgets', 'Filament\\Widgets\\Widget'),
        ];
    }

    /**
     * The concrete subclasses of $base directly in <Module>\Filament\<directory>.
     * Resources\ProductResource\Pages\* are pages of a resource, not standalone pages.
     *
     * @param  list<string>  $classes
     * @return list<string>
     */
    private static function ofKind(Module $module, array $classes, string $directory, string $base): array
    {
        if (! class_exists($base)) {
            return [];
        }

        $prefix = $module->namespace().'\\Filament\\'.$directory.'\\';
        $found = [];

        foreach ($classes as $class) {
            if (! str_starts_with($class, $prefix) || str_contains(substr($class, strlen($prefix)), '\\')) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isSubclassOf($base) && ! $reflection->isAbstract()) {
                $found[] = $class;
            }
        }

        return $found;
    }
}
