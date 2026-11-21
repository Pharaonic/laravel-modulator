<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Autoload;

use Illuminate\Contracts\Foundation\Application;
use Pharaonic\Laravel\Modulator\Module;
use Throwable;

/**
 * The class factory name resolver for module models (Laravel 8+), installed
 * once when a module is enabled, so HasFactory works without newFactory():
 *
 * - Modules\Shop\Models\Product => Modules\Shop\Database\Factories\ProductFactory;
 * - legacy (1.x) App\Modules\Blog\Models\Post => App\Modules\Blog\database\factories\PostFactory.
 *
 * Every other model resolves exactly as Laravel does. (1.x sent those back to
 * Factory::resolveFactoryName(), which called the same resolver again:
 * infinite recursion.)
 *
 * @internal
 */
final class FactoryResolver
{
    public const FACTORY = 'Illuminate\\Database\\Eloquent\\Factories\\Factory';

    /**
     * @param  array<string, bool>  $namespaces  Module namespace => legacy.
     */
    public static function install(Application $app, array $namespaces): void
    {
        $factory = self::FACTORY;

        if ($namespaces === [] || ! class_exists($factory)) {
            return;
        }

        $callback = [$factory, 'guessFactoryNamesUsing'];

        if (! is_callable($callback)) {
            return;
        }

        $callback(static function (string $model) use ($app, $namespaces, $factory): string {
            try {
                $appNamespace = $app->getNamespace();
            } catch (Throwable $e) {
                $appNamespace = 'App\\';
            }

            $factoryNamespace = get_class_vars($factory)['namespace'] ?? null;

            return self::resolve($model, $namespaces, $appNamespace, is_string($factoryNamespace) ? $factoryNamespace : 'Database\\Factories\\');
        });
    }

    /**
     * @param  array<string, bool>  $namespaces  Module namespace => legacy.
     */
    public static function resolve(string $model, array $namespaces, string $appNamespace, string $factoryNamespace): string
    {
        uksort($namespaces, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });

        foreach ($namespaces as $namespace => $legacy) {
            if (! str_starts_with($model, $namespace.'\\')) {
                continue;
            }

            if ($legacy) {
                return $namespace.'\\database\\factories\\'.substr($model, (int) strrpos($model, '\\') + 1).'Factory';
            }

            $relative = substr($model, strlen($namespace) + 1);
            $relative = str_starts_with($relative, 'Models\\') ? substr($relative, 7) : $relative;

            return $namespace.'\\Database\\Factories\\'.$relative.'Factory';
        }

        // Laravel's default (Factory::resolveFactoryName without a custom resolver).
        $name = str_starts_with($model, $appNamespace.'Models\\')
            ? substr($model, strlen($appNamespace.'Models\\'))
            : (str_starts_with($model, $appNamespace) ? substr($model, strlen($appNamespace)) : $model);

        return $factoryNamespace.$name.'Factory';
    }
}
