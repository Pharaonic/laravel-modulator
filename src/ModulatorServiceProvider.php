<?php

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Modulator\Packages\{
    Translatable\Commands\TranslatableMake,
};
use Pharaonic\Laravel\Modulator\Core\Commands\{
    DBSeed,
    Make,
    MakeCast,
    MakeChannel,
    MakeCommand,
    MakeComponent,
    MakeController,
    MakeEvent,
    MakeException,
    MakeFactory,
    MakeJob,
    MakeListener,
    MakeMail,
    MakeMiddleware,
    MakeMigration,
    MakeModel,
    MakeNotification,
    MakeObserver,
    MakePolicy,
    MakeProvider,
    MakeRequest,
    MakeResource,
    MakeRule,
    MakeSeeder,
    MakeTest,
    Migrate,
    MigrateFresh,
    MigrateRefresh,
    MigrateReset,
    MigrateRollback,
    MigrateStatus,
    ModulesList,
    RouteList,
    Test
};

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

    /**
     * Publish Config + Register Commands
     *
     * @return void
     */
    public function console()
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('modulator.php'),
        ], ['config', 'pharaonic', 'modulator']);


        // Commands (Modulator)
        $this->commands([
            DBSeed::class,
            Make::class,
            MakeCast::class,
            MakeChannel::class,
            MakeCommand::class,
            MakeComponent::class,
            MakeController::class,
            MakeEvent::class,
            MakeException::class,
            MakeFactory::class,
            MakeJob::class,
            MakeListener::class,
            MakeMail::class,
            MakeMiddleware::class,
            MakeMigration::class,
            MakeModel::class,
            MakeNotification::class,
            MakeObserver::class,
            MakePolicy::class,
            MakeProvider::class,
            MakeRequest::class,
            MakeResource::class,
            MakeRule::class,
            MakeSeeder::class,
            MakeTest::class,
            Migrate::class,
            MigrateFresh::class,
            MigrateRefresh::class,
            MigrateReset::class,
            MigrateRollback::class,
            MigrateStatus::class,
            ModulesList::class,
            RouteList::class,
            Test::class
        ]);

        // Load External Package's Commands
        $this->registerPackagesCommands();
    }

    /**
     * Register providers list
     *
     * @return void
     */
    protected function registerProviders()
    {
        $modules = modules();

        if (count($modules) > 0) {
            // CREATE MAIN SERVICE PROVIDER
            if (!file_exists($SP = app_path('Modules/ServiceProvider.php')))
                File::copy(
                    str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/Core/Commands/stubs/ServiceProvider.php'),
                    $SP
                );

            foreach ($modules as $module) {
                foreach (getFiles($path = module_path($module, 'Providers')) as $provider) {
                    $provider = str_replace($path . '/', '', substr($provider, 0, -4));
                    $this->app->register('App\Modules\\' . str_replace('/', '\\', $module) . '\Providers\\' . $provider);
                }
            }
        }
    }

    /**
     * Register all external packages CLI commands.
     *
     * @return void
     */
    protected function registerPackagesCommands()
    {
        // Translatable Package
        if (trait_exists('Pharaonic\Laravel\Translatable\Translatable')) {
            $this->commands(TranslatableMake::class);
        }
    }
}
