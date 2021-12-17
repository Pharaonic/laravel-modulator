<?php

namespace Pharaonic\Laravel\Modulator;

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
}
