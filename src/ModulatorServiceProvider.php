<?php

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Support\ServiceProvider;
use Laravel\Prompts\Output\ConsoleOutput;
use Pharaonic\Laravel\Modulator\Packages\{
    Translatable\Commands\TranslatableMake,
};
use Pharaonic\Laravel\Modulator\Core\Commands\{
    DBSeed,
    Delete,
    Discover,
    Make,
    MakeAction,
    MakeCast,
    MakeChannel,
    MakeCommand,
    MakeComponent,
    MakeClass,
    MakeController,
    MakeEnum,
    MakeEvent,
    MakeException,
    MakeFactory,
    MakeInterface,
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
    MakeScope,
    MakeSeeder,
    MakeService,
    MakeTest,
    MakeTrait,
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
            MakeAction::class,
            MakeCast::class,
            MakeChannel::class,
            MakeCommand::class,
            MakeComponent::class,
            MakeClass::class,
            MakeController::class,
            MakeEnum::class,
            MakeEvent::class,
            MakeException::class,
            MakeFactory::class,
            MakeInterface::class,
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
            MakeScope::class,
            MakeSeeder::class,
            MakeService::class,
            MakeTest::class,
            MakeTrait::class,
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

        $output = new ConsoleOutput();

        // Register the exists providers
        foreach ($finder->list as $provider) {
            try {
                if (class_exists($provider)) {
                    $this->app->register($provider);
                }
            } catch (\Exception $e) {
                $output->writeln("<comment>Failed to register provider: {$provider}, you need to run <info>module:discover</info> again.");
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
