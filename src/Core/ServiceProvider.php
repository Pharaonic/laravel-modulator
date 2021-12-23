<?php

namespace Pharaonic\Laravel\Modulator\Core;

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
        $this->loadViews();
        $this->loadMigrations();
        $this->registerCommands();
        $this->loadTranslations();
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

    protected function registerCommands()
    {
        if (!static::$module) return;

        if ($this->app->runningInConsole()) {
            if (file_exists($commands = module_path(static::$module, 'Commands'))) {
                $module = 'App\Modules\\' . static::$module . '\Commands\\';
                $commands = array_map(function ($command) use ($module) {
                    return $module . str_replace('.php', '', $command);
                }, getFiles($commands));

                $this->commands($commands);
            }

            $console = module_path(static::$module, 'routes/console.php');
            if (file_exists($console)) require $console;
        }
    }

    protected function loadTranslations()
    {
        if (file_exists($dir = module_path(static::$module, 'resources/lang')))
            $this->loadTranslationsFrom($dir, studlyToSlug(static::$module));
    }

    protected function loadMigrations()
    {
        if (file_exists($dir = module_path(static::$module, 'database/migrations')))
            $this->loadMigrationsFrom($dir);
    }

    protected function loadViews()
    {
        if (file_exists($dir = module_path(static::$module, 'resources/views')))
            $this->loadViewsFrom($dir, studlyToSlug(static::$module));
    }
}
