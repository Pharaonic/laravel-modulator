<?php

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Modulator\Packages\{
    Translatable\Commands\TranslatableMake,
};
use Pharaonic\Laravel\Modulator\Core\Commands\{
    DBSeed,
    Delete,
    Discover,
    Make,
    MakeCast,
    MakeChannel,
    MakeCommand,
    MakeComponent,
    MakeClass,
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
    MigrateStatus,
    ModulesList,
    RouteList,
    Test
};
use Pharaonic\Laravel\Modulator\Core\ModulesFinder;

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
        $this->app->singleton(ModulesFinder::class);
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
            Delete::class,
            Discover::class,
            MakeCast::class,
            MakeChannel::class,
            MakeCommand::class,
            MakeComponent::class,
            MakeClass::class,
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
        $finder = app(ModulesFinder::class);

        // Cache all current providers
        if (!$finder->cached) {
            $finder->build();
        }

        // Register the exists providers
        foreach ($finder->list as $provider) {
            if (file_exists(base_path('a' . substr(str_replace('\\', '/', $provider), 1) . '.php'))) {
                $this->app->register($provider);
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
