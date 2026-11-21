<?php

namespace Pharaonic\Laravel\Modulator\Tests;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use Pharaonic\Laravel\Modulator\ModulatorServiceProvider;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\ConfigEditor;
use Pharaonic\Laravel\Modulator\Support\Files;

/**
 * Every test runs against its own copy of the Testbench skeleton, so modules,
 * caches and published copies never leak between tests.
 */
abstract class TestCase extends Orchestra
{
    use WritesFiles;

    /**
     * @var string
     */
    protected $base;

    /**
     * @var array<string, mixed>
     */
    protected $modulator = [];

    protected function setUp(): void
    {
        $this->base = self::temporaryDirectory('modulator-app');
        (new Filesystem)->copyDirectory(__DIR__.'/../vendor/orchestra/testbench-core/laravel', $this->base);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('SHELL_INTERACTIVE');

        Files::deleteDirectory($this->base);
    }

    protected function getBasePath()
    {
        return $this->base;
    }

    protected function getPackageProviders($app)
    {
        return [ModulatorServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));

        // The list module:enable and module:disable write, once published.
        $published = $this->base.'/config/pharaonic/modulator.php';
        $disabled = is_file($published) ? (new ConfigEditor)->read((string) file_get_contents($published)) : [];

        $app['config']->set('pharaonic.modulator', array_replace([
            'disabled' => $disabled,
            'pools' => [
                'local' => ['path' => $this->base.'/Modules', 'namespace' => 'Modules', 'priority' => 100],
                'app' => ['path' => $this->base.'/app/Modules', 'namespace' => 'App\\Modules', 'priority' => 90, 'legacy' => true],
            ],
            'discovery' => ['cache' => false],
            'generation' => ['pool' => 'local', 'stubs' => null],
        ], $this->modulator));
    }

    /**
     * Boot a fresh application on the same base path, e.g. after writing modules.
     *
     * @param  array<string, mixed>  $modulator
     */
    protected function reload(array $modulator = []): void
    {
        $this->modulator = array_replace($this->modulator, $modulator);
        $this->refreshApplication();
    }

    /**
     * Run a command and return [exit code, output].
     *
     * @param  array<string, mixed>  $parameters
     * @return array{0: int, 1: string}
     */
    protected function command(string $command, array $parameters = [], bool $interactive = false): array
    {
        putenv($interactive ? 'SHELL_INTERACTIVE=1' : 'SHELL_INTERACTIVE');

        $status = \Illuminate\Support\Facades\Artisan::call($command, $parameters + ($interactive ? [] : ['--no-interaction' => true]));

        putenv('SHELL_INTERACTIVE');

        return [$status, \Illuminate\Support\Facades\Artisan::output()];
    }

    /**
     * Write a module.php definition.
     *
     * @param  array<string, list<string>>  $definition
     */
    protected function define(string $directory, array $definition): void
    {
        $this->write($directory.'/module.php', '<?php return '.var_export($definition, true).';');
    }

    protected function registry(): ModuleRegistry
    {
        return $this->app->make(ModuleRegistry::class);
    }

    /**
     * Write a module provider that records it was registered and booted.
     */
    protected function writeProvider(string $directory, string $namespace, string $name): void
    {
        $this->write($directory.'/app/Providers/'.$name.'ServiceProvider.php', <<<PHP
<?php

namespace {$namespace}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class {$name}ServiceProvider extends ServiceProvider
{
    public function register()
    {
        \$this->app->instance('{$namespace}.registered', true);
    }

    public function boot()
    {
        \$this->app->instance('{$namespace}.booted', true);
    }
}
PHP);
    }

    /**
     * Map App\ to the skeleton's app/ directory, as an application's composer.json does.
     */
    protected function autoloadApp(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->addPsr4('App\\', $this->base.'/app', true);

            return;
        }
    }
}
