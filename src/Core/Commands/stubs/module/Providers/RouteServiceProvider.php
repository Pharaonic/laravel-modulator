<?php

namespace App\Modules\{{ module-name-namespace }}\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Modules\\{{ module-name-namespace-double }}\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $isConsole = $this->app->runningInConsole();
        $module = studlyToSlug(AppServiceProvider::$module);

        $this->routes(function () use ($isConsole, $module) {
            $this->mapApiRoutes($isConsole, $module);
            $this->mapWebRoutes($isConsole, $module);
        });
    }

    /**
     * Load API routes
     *
     * @param boolean $isConsole
     * @param string $module
     * @return void
     */
    private function mapApiRoutes(bool $isConsole, string $module)
    {
        $routes = Route::middleware('api')
            ->namespace($this->namespace)
            ->prefix('api/' . $module)
            ->name($module . '.');

        if ($isConsole && in_array('module:routes', $_SERVER['argv'] ?? [])) {
            $routes->name('[Module:' . $module . '] ');
        }

        $routes->group(module_path(AppServiceProvider::$module, 'routes/api.php'));
    }

    /**
     * Load Web routes
     *
     * @param boolean $isConsole
     * @param string $module
     * @return void
     */
    private function mapWebRoutes(bool $isConsole, string $module)
    {
        $routes = Route::middleware('web')
            ->namespace($this->namespace)
            ->prefix($module)
            ->name($module . '.');

        if ($isConsole && in_array('module:routes', $_SERVER['argv'] ?? [])) {
            $routes->name('[Module:' . $module . '] ');
        }

        $routes->group(module_path(AppServiceProvider::$module, 'routes/web.php'));
    }
}
