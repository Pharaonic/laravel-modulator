<?php

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Support\ServiceProvider;
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
    MigrateFresh,
    MigrateRefresh,
    MigrateReset,
    MigrateRollback,
    MigrateStatus,
    ModulesList,
    RouteList,
    Test
};
use Pharaonic\Laravel\Modulator\Core\Commands\Packages\MakeTranslatable;

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

    /**
     * Register all external packages CLI commands.
     *
     * @return void
     */
    protected function registerPackagesCommands()
    {
        // Translatable Package
        if (trait_exists('Pharaonic\Laravel\Translatable\Translatable')) {
            $this->commands(MakeTranslatable::class);
        }
    }
}
