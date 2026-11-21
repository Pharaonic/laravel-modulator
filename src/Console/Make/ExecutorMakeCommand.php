<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\ExecutorPaths;
use Pharaonic\Laravel\Modulator\Support\ViteEntries;

/**
 * module:make:executor: laravel-executor's make:executor, written into the
 * module's executors directory, which is added to the executor pool.
 */
final class ExecutorMakeCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:make:executor
                            {module : The module identity, e.g. "Shop" or "Commerce/Checkout"}
                            {name : The name of the executor}';

    /**
     * @var string
     */
    protected $description = 'Create a new executor in a module';

    public function handle(): int
    {
        if (! $this->laravel->bound(ExecutorPaths::MANAGER)) {
            $this->error('Executors need pharaonic/laravel-executor. Install it with "composer require pharaonic/laravel-executor".');

            return 1;
        }

        $module = $this->resolveModule($this->argument('module'));

        if ($module === null) {
            return 1;
        }

        if ($module->package() !== null) {
            $this->error(sprintf(
                'Module [%s] comes from the package [%s], which must not be changed. Run "php artisan module:publish %s" to get a local copy, then generate into it.',
                $module->id(),
                $module->package(),
                $module->id()
            ));

            return 1;
        }

        $directory = ViteEntries::relative($module->path(ExecutorPaths::DIRECTORY), $this->laravel->basePath());

        if (str_starts_with($directory, '/') || preg_match('/^[A-Za-z]:\//', $directory) === 1) {
            $this->error(sprintf('Module [%s] is outside the project directory, where make:executor writes.', $module->id()));

            return 1;
        }

        return $this->call('make:executor', ['name' => $this->argument('name'), '--path' => $directory]);
    }
}
