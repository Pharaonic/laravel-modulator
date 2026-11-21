<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Illuminate\Foundation\Application;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\ExecutorPaths;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class ExecutorTest extends TestCase
{
    private const PACKAGE = 'Pharaonic\\Laravel\\Executor\\ExecutorServiceProvider';

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return array_map(static function (string $path): string {
            return str_replace('\\', '/', $path);
        }, app(ExecutorPaths::MANAGER)->getPool()->getPaths());
    }

    public function test_the_generator_writes_an_executor_that_execute_runs(): void
    {
        $this->requiresPackage(self::PACKAGE, 'pharaonic/laravel-executor');
        $this->directory('Modules/Shop/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:executor', ['module' => 'Shop', 'name' => 'ImportCountries']);
        $this->assertSame(0, $status, $output);

        $files = glob($this->base.'/Modules/Shop/executors/*_import_countries.php') ?: [];
        $this->assertCount(1, $files, 'make:executor wrote it into the module.');
        $this->write('Modules/Shop/executors/'.basename($files[0]), str_replace("    public function up(): void\n    {\n        //", "    public function up(): void\n    {\n        cache()->increment('countries');", (string) file_get_contents($files[0])));

        $this->reload();
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('pharaonic.executor.connection', 'testing');
        $this->assertSame(0, $this->command('migrate', ['--database' => 'testing'])[0]);
        $this->assertContains(str_replace('\\', '/', $this->base).'/Modules/Shop/executors', $this->paths());

        [$status, $output] = $this->command('execute');
        $this->assertSame(0, $status, $output);
        $this->assertSame(1, cache('countries'), 'execute ran the module executor.');
    }

    public function test_only_enabled_modules_with_an_executors_directory_are_added(): void
    {
        $this->requiresPackage(self::PACKAGE, 'pharaonic/laravel-executor');
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/executors');
        $this->directory('Modules/Blog/app');
        $this->directory('Modules/Blog/executors');
        $this->directory('Modules/Admin/app');
        $this->reload(['disabled' => ['Blog']]);

        $paths = $this->paths();
        $base = str_replace('\\', '/', $this->base);
        $this->assertContains($base.'/Modules/Shop/executors', $paths);
        $this->assertNotContains($base.'/Modules/Blog/executors', $paths, 'A disabled module is left out.');
        $this->assertNotContains($base.'/Modules/Admin/executors', $paths, 'A module without executors/ is left out.');
    }

    public function test_without_laravel_executor_nothing_is_added_and_the_generator_refuses(): void
    {
        $this->withoutPackage(self::PACKAGE, 'pharaonic/laravel-executor');
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/executors');
        $this->reload();

        $app = new Application($this->base);
        ExecutorPaths::register($app, Modules::find('Shop'));
        $this->assertFalse($app->bound(ExecutorPaths::MANAGER));

        [$status, $output] = $this->command('module:make:executor', ['module' => 'Shop', 'name' => 'ImportCountries']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('composer require pharaonic/laravel-executor', $output);
        $this->assertSame([], glob($this->base.'/Modules/Shop/executors/*.php') ?: []);
    }
}
