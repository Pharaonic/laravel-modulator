<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use LogicException;
use Pharaonic\Laravel\Modulator\Autoload\Autoloader;
use Pharaonic\Laravel\Modulator\Autoload\FactoryResolver;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\Discovery\PoolResolver;
use Pharaonic\Laravel\Modulator\Support\DisabledList;
use Pharaonic\Laravel\Modulator\Support\ModuleResources;
use Pharaonic\Laravel\Modulator\Support\ViteEntries;
use RuntimeException;

final class ModulatorServiceProvider extends ServiceProvider
{
    private ?ModuleRegistry $registry = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/modulator.php', 'pharaonic.modulator');

        $config = $this->app->make('config');

        if (! $config instanceof Repository) {
            throw new LogicException('The configuration repository is not available.');
        }

        $vendor = $this->app->basePath('vendor');
        $autoloader = new Autoloader($vendor);
        $manifest = new Manifest(
            new Filesystem,
            new PoolResolver($this->app, $vendor),
            $autoloader,
            $this->app->bootstrapPath('cache'.DIRECTORY_SEPARATOR.'modulator.php'),
            $config->get('pharaonic.modulator.pools', []),
            (bool) $config->get('pharaonic.modulator.discovery.cache', true)
        );

        $data = $manifest->load();
        $registry = ModuleRegistry::fromManifest($data, $config->get('pharaonic.modulator.disabled', []));
        $this->registry = $registry;

        $this->app->instance(Autoloader::class, $autoloader);
        $this->app->instance(Manifest::class, $manifest);
        $this->app->instance(ModuleRegistry::class, $registry);
        $this->app->bind(DisabledList::class, function (): DisabledList {
            return new DisabledList($this->app->configPath('pharaonic'.DIRECTORY_SEPARATOR.'modulator.php'), $this->app->storagePath());
        });

        $autoloader->register($registry, $data['classmap']);

        foreach ($registry->enabled() as $module) {
            $this->app->register(new ModuleResources($this->app, $module, array_keys($data['classmap'][$module->id()] ?? [])), true);

            foreach ($this->providers($module) as $provider) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        $namespaces = [];

        foreach ($this->registry === null ? [] : $this->registry->enabled() as $module) {
            $namespaces[$module->namespace()] = $module->source()->legacy;
        }

        FactoryResolver::install($this->app, $namespaces);

        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->directive('moduleVite', static function (string $expression): string {
                return '<?php echo \\'.ViteEntries::class.'::tags('.$expression.'); ?>';
            });
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/modulator.php' => $this->app->configPath('pharaonic'.DIRECTORY_SEPARATOR.'modulator.php'),
        ], ['pharaonic', 'laravel-modulator', 'pharaonic-config', 'modulator-config', 'config', 'modulator']);

        // Stubs are an opt-in customization, so they are not in the "pharaonic" and "laravel-modulator" tags.
        $this->publishes([
            Support\Stubs::packagePath() => $this->app->basePath('stubs'.DIRECTORY_SEPARATOR.'modulator'),
        ], ['modulator-stubs', 'pharaonic-stubs']);

        $this->commands([
            // Architecture
            Console\Architecture\CheckCommand::class,
            Console\Architecture\DoctorCommand::class,
            Console\Architecture\GraphCommand::class,
            // Database
            Console\Database\MigrateCommand::class,
            Console\Database\MigrateFreshCommand::class,
            Console\Database\MigrateRefreshCommand::class,
            Console\Database\MigrateResetCommand::class,
            Console\Database\MigrateRollbackCommand::class,
            Console\Database\MigrateStatusCommand::class,
            Console\Database\SeedCommand::class,
            // Development
            Console\Development\RoutesCommand::class,
            Console\Development\TestCommand::class,
            Console\Development\ViteCommand::class,
            // Discovery
            Console\Discovery\CacheCommand::class,
            Console\Discovery\ClearCommand::class,
            Console\Discovery\DiscoverCommand::class,
            // Make
            Console\Make\ActionMakeCommand::class,
            Console\Make\CacheTableCommand::class,
            Console\Make\CastMakeCommand::class,
            Console\Make\ChannelMakeCommand::class,
            Console\Make\ClassMakeCommand::class,
            Console\Make\ComponentMakeCommand::class,
            Console\Make\ConfigMakeCommand::class,
            Console\Make\ConsoleMakeCommand::class,
            Console\Make\ControllerMakeCommand::class,
            Console\Make\EventMakeCommand::class,
            Console\Make\ExceptionMakeCommand::class,
            Console\Make\FactoryMakeCommand::class,
            Console\Make\FilamentResourceMakeCommand::class,
            Console\Make\InterfaceMakeCommand::class,
            Console\Make\JobMakeCommand::class,
            Console\Make\JobMiddlewareMakeCommand::class,
            Console\Make\ListenerMakeCommand::class,
            Console\Make\LivewireMakeCommand::class,
            Console\Make\MailMakeCommand::class,
            Console\Make\MiddlewareMakeCommand::class,
            Console\Make\MigrationMakeCommand::class,
            Console\Make\ModelMakeCommand::class,
            Console\Make\ModuleMakeCommand::class,
            Console\Make\NotificationMakeCommand::class,
            Console\Make\NotificationsTableCommand::class,
            Console\Make\ObserverMakeCommand::class,
            Console\Make\PolicyMakeCommand::class,
            Console\Make\ProviderMakeCommand::class,
            Console\Make\QueueBatchesTableCommand::class,
            Console\Make\QueueFailedTableCommand::class,
            Console\Make\QueueTableCommand::class,
            Console\Make\RequestMakeCommand::class,
            Console\Make\ResourceMakeCommand::class,
            Console\Make\RuleMakeCommand::class,
            Console\Make\ScopeMakeCommand::class,
            Console\Make\SeederMakeCommand::class,
            Console\Make\ServiceMakeCommand::class,
            Console\Make\SessionTableCommand::class,
            Console\Make\TestMakeCommand::class,
            Console\Make\TraitMakeCommand::class,
            Console\Make\ViewMakeCommand::class,
            // Management
            Console\Management\DeleteCommand::class,
            Console\Management\ListCommand::class,
            // Sources
            Console\Sources\PoolsCommand::class,
            Console\Sources\PublishCommand::class,
            Console\Sources\SourceCommand::class,
            // State
            Console\State\CurrentCommand::class,
            Console\State\DisableCommand::class,
            Console\State\EnableCommand::class,
            Console\State\StatusCommand::class,
            Console\State\UseCommand::class,
        ]);

        $this->optimizeWithLaravel();
    }

    /**
     * "optimize" also caches the module manifest, "optimize:clear" clears it.
     */
    private function optimizeWithLaravel(): void
    {
        $events = $this->app->make('events');

        if (! $events instanceof Dispatcher) {
            return;
        }

        $events->listen(CommandFinished::class, function (CommandFinished $event): void {
            $output = $event->output;

            if ($event->exitCode !== 0 || $output === null || ! in_array($event->command, ['optimize', 'optimize:clear'], true)) {
                return;
            }

            $manifest = $this->app->make(Manifest::class);

            if (! $manifest instanceof Manifest) {
                return;
            }

            try {
                if ($event->command === 'optimize') {
                    $manifest->rebuild();
                    $output->writeln('Module manifest cached.');
                } else {
                    $manifest->clear();
                    $output->writeln('Module manifest cleared.');
                }
            } catch (RuntimeException $e) {
                $output->writeln('<error>Module manifest not cached: '.$e->getMessage().'</error>');
            }
        });
    }

    /**
     * The provider classes a module declares by convention.
     *
     * A module has at most one provider, Providers/<Name>ServiceProvider; a
     * module without one is valid. Legacy (1.x) modules register every class of
     * their Providers/ folder, as 1.x did.
     *
     * @return list<string>
     */
    private function providers(Module $module): array
    {
        $namespace = $module->namespace().'\\Providers\\';

        if (! $module->source()->legacy) {
            $provider = $namespace.$module->name().'ServiceProvider';

            return class_exists($provider) ? [$provider] : [];
        }

        $files = glob($module->path('Providers').DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files, SORT_STRING);
        $providers = [];

        foreach ($files as $file) {
            $class = $namespace.basename($file, '.php');

            if (class_exists($class) && is_subclass_of($class, ServiceProvider::class)) {
                $providers[] = $class;
            }
        }

        return $providers;
    }
}
