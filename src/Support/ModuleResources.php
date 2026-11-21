<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Support;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as FoundationApplication;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Modulator\Module;
use ReflectionClass;

/**
 * Loads the conventional resources of one enabled module, with Laravel's own
 * provider helpers, so route, config and view caches behave as for any package.
 *
 * Conventions (module root):
 * - helpers.php, required once when the module registers;
 * - config/*.php merged under "modules.<slug>.<file>";
 * - resources/views (views "<slug>::"), lang or resources/lang (translations "<slug>::", JSON files);
 * - database/migrations;
 * - routes/web.php ("web" middleware) and routes/api.php ("api" middleware, "api" prefix),
 *   each route tagged with the module identity in its action ("module").
 *
 * - with Livewire installed, the components of app/Livewire ("<slug>::<name>");
 * - with Filament installed, the resources, pages and widgets of app/Filament, in their panels;
 * - in the console, the commands of app/Console/Commands and routes/console.php;
 * - on Laravel 6 and 7, the closure factories of database/factories.
 *
 * Legacy (1.x) modules keep their 1.x layout: helpers.php, config/, lang/,
 * resources/views, database/migrations, Commands/ and routes/console.php; their
 * own RouteServiceProvider loads their HTTP routes.
 *
 * @internal
 */
final class ModuleResources extends ServiceProvider
{
    /**
     * @param  Application  $app
     * @param  list<string>  $classes  The module classes (from the manifest class map).
     */
    public function __construct($app, private Module $module, private array $classes = [])
    {
        parent::__construct($app);
    }

    public function register(): void
    {
        FilamentComponents::register($this->app, $this->module, $this->classes);

        if (is_file($helpers = $this->module->path('helpers.php'))) {
            require_once $helpers;
        }

        $config = $this->module->path('config');

        if (! is_dir($config)) {
            return;
        }

        $files = glob($config.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $this->mergeConfigFrom($file, 'modules.'.$this->module->slug().'.'.basename($file, '.php'));
        }
    }

    public function boot(): void
    {
        $slug = $this->module->slug();
        $legacy = $this->module->source()->legacy;

        if (is_dir($views = $this->module->path('resources/views'))) {
            $this->loadViewsFrom($views, $slug);
        }

        $lang = $this->module->path('lang');

        if (! $legacy && ! is_dir($lang)) {
            $lang = $this->module->path('resources/lang');
        }

        if (is_dir($lang)) {
            $this->loadTranslationsFrom($lang, $slug);
            $this->loadJsonTranslationsFrom($lang);
        }

        if (is_dir($migrations = $this->module->path('database/migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        $this->loadClosureFactories();
        LivewireComponents::register($this->app, $this->module, $this->classes);

        if ($legacy) {
            $this->bootLegacyConsole();

            return;
        }

        $this->bootConsole();

        $this->app->booted(function (): void {
            $this->loadRoutes();
        });
    }

    private function loadRoutes(): void
    {
        if ($this->app instanceof FoundationApplication && $this->app->routesAreCached()) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make('router');
        $id = $this->module->id();
        $loaded = false;

        if (is_file($web = $this->module->path('routes/web.php'))) {
            $router->group(['middleware' => 'web', 'module' => $id], $web);
            $loaded = true;
        }

        if (is_file($api = $this->module->path('routes/api.php'))) {
            $router->group(['middleware' => 'api', 'prefix' => 'api', 'module' => $id], $api);
            $loaded = true;
        }

        if ($loaded) {
            $router->getRoutes()->refreshNameLookups();
            $router->getRoutes()->refreshActionLookups();
        }
    }

    private function bootConsole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $prefix = $this->module->namespace().'\\Console\\Commands\\';
        $commands = [];

        foreach ($this->classes as $class) {
            if (str_starts_with($class, $prefix) && class_exists($class) && is_subclass_of($class, Command::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $commands[] = $class;
            }
        }

        $this->commands($commands);

        if (is_file($console = $this->module->path('routes/console.php'))) {
            require $console;
        }
    }

    /**
     * Laravel 6 and 7 define factories as closures in database/factories.
     */
    private function loadClosureFactories(): void
    {
        $factory = 'Illuminate\\Database\\Eloquent\\Factory';
        $path = $this->module->path('database/factories');

        if (! class_exists($factory) || ! is_dir($path)) {
            return;
        }

        $load = static function ($factories) use ($path): void {
            if (is_object($factories) && method_exists($factories, 'load')) {
                $factories->load($path);
            }
        };

        if ($this->app->resolved($factory)) {
            $load($this->app->make($factory));

            return;
        }

        $this->app->afterResolving($factory, $load);
    }

    private function bootLegacyConsole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $directory = $this->module->path('Commands');

        if (is_dir($directory)) {
            $commands = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
                $class = $this->module->namespace().'\\Commands\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

                if (class_exists($class) && is_subclass_of($class, Command::class)) {
                    $commands[] = $class;
                }
            }

            sort($commands, SORT_STRING);
            $this->commands($commands);
        }

        if (is_file($console = $this->module->path('routes/console.php'))) {
            require $console;
        }
    }
}
