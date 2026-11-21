<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use ReflectionClass;

/**
 * Registers the Livewire components of a module, when Livewire is installed:
 * every concrete Livewire\Component under <Module>\Http\Livewire, named
 * "<slug>::<kebab path>" (Modules\Shop\Http\Livewire\Admin\UserTable is
 * "shop::admin.user-table"). The classes come from the module class map, so
 * nothing is scanned.
 *
 * @internal
 */
final class LivewireComponents
{
    public const COMPONENT = 'Livewire\\Component';

    /**
     * The namespace that holds a module's Livewire components, with a trailing "\".
     */
    public static function namespace(Module $module): string
    {
        return $module->namespace().'\\Http\\Livewire\\';
    }

    /**
     * The name of a component class of the module, or null when the class is outside its Livewire namespace.
     */
    public static function alias(Module $module, string $class): ?string
    {
        $prefix = self::namespace($module);

        if (! str_starts_with($class, $prefix) || strlen($class) === strlen($prefix)) {
            return null;
        }

        $segments = array_map(static function (string $segment): string {
            return Str::kebab($segment);
        }, explode('\\', substr($class, strlen($prefix))));

        return $module->slug().'::'.implode('.', $segments);
    }

    /**
     * @param  list<string>  $classes  The module classes (from the manifest class map).
     */
    public static function register(Application $app, Module $module, array $classes): void
    {
        if (! $app->bound('livewire') || ! class_exists(self::COMPONENT)) {
            return;
        }

        $livewire = $app->make('livewire');

        if (! is_object($livewire) || ! method_exists($livewire, 'component')) {
            return;
        }

        foreach ($classes as $class) {
            $alias = self::alias($module, $class);

            if ($alias === null || ! class_exists($class) || ! is_subclass_of($class, self::COMPONENT) || (new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $livewire->component($alias, $class);
        }
    }
}
