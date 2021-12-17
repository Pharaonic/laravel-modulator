<?php

namespace Pharaonic\Laravel\Modulator;

use App\Modules\Customers\Providers\AppServiceProvider;
use Illuminate\Support\ServiceProvider;

class ModulatorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Config
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'modulator');

        // Providers
        $this->registerProviders();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Console
        if ($this->app->runningInConsole())
            $this->console();
    }

    public function console()
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('modulator.php'),
        ], ['config', 'pharaonic', 'modulator']);
    }

    protected function registerProviders()
    {
        foreach (modules() as $module) {
            if (file_exists($providers = module_path($module, 'Providers'))) {
                $module = 'App\Modules\\' . $module . '\Providers\\';

                foreach (getFiles($providers) as $provider) {
                    $provider = $module . substr($provider, 0, -4);
                    $this->app->register($provider);
                }
            }
        }
    }
}
