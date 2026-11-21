<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;

/**
 * Runs Laravel's own make:migration into the module's database/migrations.
 */
final class MigrationMakeCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:make:migration
                            {module : The module identity (or the migration name, to use the module selected with module:use)}
                            {name? : The migration name, e.g. create_products_table}
                            {--create= : The table to be created}
                            {--table= : The table to migrate}
                            {--fullpath : Output the full path of the migration}';

    /**
     * @var string
     */
    protected $description = 'Create a new migration file in a module';

    public function handle(): int
    {
        $first = $this->argument('module');
        $second = $this->argument('name');
        [$moduleArgument, $name] = is_string($second) && $second !== '' ? [$first, $second] : [null, $first];

        if (! is_string($name) || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            $this->error('Give the migration name, in snake_case (letters, digits and underscores).');

            return 1;
        }

        $module = $this->resolveModule($moduleArgument);

        if ($module === null) {
            return 1;
        }

        if ($module->package() !== null) {
            $this->error(sprintf('Module [%s] comes from the package [%s], which must not be changed. Run "php artisan module:publish %s" first.', $module->id(), $module->package(), $module->id()));

            return 1;
        }

        $path = $module->path('database/migrations');

        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            $this->error(sprintf('Cannot create [%s].', $path));

            return 1;
        }

        return $this->call('make:migration', array_filter([
            'name' => $name,
            '--create' => $this->option('create'),
            '--table' => $this->option('table'),
            '--path' => $path,
            '--realpath' => true,
            '--fullpath' => (bool) $this->option('fullpath'),
        ], static function ($value): bool {
            return $value !== null && $value !== false;
        }));
    }
}
