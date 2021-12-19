<?php

namespace App\Modules\{{ module-name }}\Providers;

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
    // protected $namespace = 'App\\Modules\\{{ module-name }}\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->routes(function () {
            Route::middleware('api')
                ->namespace($this->namespace)
                ->name(studlyToSlug(AppServiceProvider::$module) . '.')
                ->prefix('api/' . studlyToSlug(AppServiceProvider::$module))
                ->group(module_path(AppServiceProvider::$module, 'routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->prefix(studlyToSlug(AppServiceProvider::$module))
                ->name(studlyToSlug(AppServiceProvider::$module) . '.')
                ->group(module_path(AppServiceProvider::$module, 'routes/web.php'));
        });
    }
}
