<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Contracts\Foundation\Application;
use Pharaonic\Laravel\Modulator\Module;

/**
 * Adds a module's executors directory to pharaonic/laravel-executor's pool,
 * when laravel-executor is installed, so execute, execute:rollback and
 * execute:status read the module's executors too.
 *
 * @internal
 */
final class ExecutorPaths
{
    /**
     * laravel-executor's manager binding (Executor::getPool()).
     */
    public const MANAGER = 'pharaonic.executor.manager';

    /**
     * The directory of a module's executors, relative to the module.
     */
    public const DIRECTORY = 'executors';

    public static function register(Application $app, Module $module): void
    {
        $directory = $module->path(self::DIRECTORY);

        if (! $app->bound(self::MANAGER) || ! is_dir($directory)) {
            return;
        }

        $add = static function ($manager) use ($directory): void {
            $pool = is_object($manager) && method_exists($manager, 'getPool') ? $manager->getPool() : null;

            if (is_object($pool) && method_exists($pool, 'addPath')) {
                $pool->addPath($directory);
            }
        };

        if ($app->resolved(self::MANAGER)) {
            $add($app->make(self::MANAGER));

            return;
        }

        $app->afterResolving(self::MANAGER, $add);
    }
}
