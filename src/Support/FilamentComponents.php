<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use ReflectionClass;

/**
 * Adds the Filament resources, pages and widgets of a module to the panels,
 * when Filament is installed, by directory, as Filament lays out an
 * application:
 *
 * - <Module>\Filament\{Resources,Pages,Widgets} join the default panel;
 * - <Module>\Filament\<PanelId>\{Resources,Pages,Widgets} join the panel with
 *   that id (studly: <Module>\Filament\App\Resources is the "app" panel's).
 *
 * The classes are added through Panel::configureUsing(), which a panel runs
 * when it gets its id, before it registers its routes and Livewire
 * components. Filament 3 gets the classes directly in each folder, from the
 * module class map; Filament 4 and 5 discover the folders themselves.
 *
 * @internal
 */
final class FilamentComponents
{
    public const PANEL = 'Filament\\Panel';

    public const SCHEMA = 'Filament\\Schemas\\Schema';

    /**
     * @param  list<string>  $classes  The module classes (from the manifest class map).
     */
    public static function register(Application $app, Module $module, array $classes): void
    {
        $configureUsing = [self::PANEL, 'configureUsing'];

        if (! $app->bound('filament') || ! is_callable($configureUsing)) {
            return;
        }

        call_user_func($configureUsing, static function ($panel) use ($module, $classes): void {
            if (! is_object($panel) || ! method_exists($panel, 'getId') || ! method_exists($panel, 'isDefault')) {
                return;
            }

            $id = $panel->getId();
            $directory = $panel->isDefault() ? '' : Str::studly(is_string($id) ? $id : '').'\\';

            // Filament 4 and 5 discover the module folders the way they discover
            // the application's: nested resource folders included, and
            // make:filament-resource can write into them.
            if (self::discovers()) {
                foreach (['Resources' => 'discoverResources', 'Pages' => 'discoverPages', 'Widgets' => 'discoverWidgets'] as $kind => $method) {
                    if (method_exists($panel, $method)) {
                        $panel->{$method}(self::path($module, $directory.$kind), $module->namespace().'\\Filament\\'.$directory.$kind);
                    }
                }

                return;
            }

            foreach (self::classes($module, $classes, $directory) as $method => $found) {
                if ($found !== [] && method_exists($panel, $method)) {
                    $panel->{$method}($found);
                }
            }
        });
    }

    /**
     * Whether the installed Filament discovers module folders (Filament 4 and
     * later, whose schemas live in filament/schemas) instead of taking the
     * class lists Filament 3 gets.
     */
    public static function discovers(): bool
    {
        return class_exists(self::SCHEMA);
    }

    /**
     * The folder of <Module>\Filament\<directory>, e.g. "App\Resources".
     */
    public static function path(Module $module, string $directory): string
    {
        return $module->source()->classPath().DIRECTORY_SEPARATOR.'Filament'.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $directory);
    }

    /**
     * The concrete resource, page and widget classes of the module for one panel directory.
     *
     * @param  list<string>  $classes
     * @return array{resources: list<string>, pages: list<string>, widgets: list<string>}
     */
    private static function classes(Module $module, array $classes, string $directory): array
    {
        return [
            'resources' => self::ofKind($module, $classes, $directory.'Resources', 'Filament\\Resources\\Resource'),
            'pages' => self::ofKind($module, $classes, $directory.'Pages', 'Filament\\Pages\\Page'),
            'widgets' => self::ofKind($module, $classes, $directory.'Widgets', 'Filament\\Widgets\\Widget'),
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
