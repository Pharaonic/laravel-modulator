<?php

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Create a new service provider instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        parent::__construct($app);

        // Modulator Loaders
        $this->loadConfig();
    }

    /**
     * Module's configurations
     *
     * @var string
     */
    public static $module = null;

    protected function loadConfig()
    {
        if (!static::$module) return;

        if (file_exists($config = module_config_path(static::$module))) {
            foreach (getFiles($config) as $file) {
                $this->mergeConfigFrom(
                    module_config_path(static::$module) . DIRECTORY_SEPARATOR . $file,
                    'modules.' . studlyToSlug(static::$module) . '.' . str_replace('.php', '', $file)
                );
            }
        }
    }
}
