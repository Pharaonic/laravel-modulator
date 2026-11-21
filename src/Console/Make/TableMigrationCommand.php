<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\Files;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;

/**
 * The base of module:make:cache-table, module:make:notifications-table,
 * module:make:queue-batches-table, module:make:queue-failed-table,
 * module:make:queue-table and module:make:session-table: the migrations of
 * Laravel's framework tables, written into a module.
 *
 * The migration matches the running Laravel version: an anonymous class from
 * Laravel 8.37, and the columns its drivers use.
 *
 * @internal
 */
abstract class TableMigrationCommand extends Command
{
    use ResolvesModules;

    /**
     * The stub, e.g. "migration.jobs.stub".
     */
    protected string $stub = '';

    /**
     * The configuration key holding the table name, or null.
     */
    protected ?string $tableKey = null;

    /**
     * The table name when the configuration has none.
     */
    protected string $table = '';

    /**
     * Why this migration cannot be written here (a Laravel version), or null.
     */
    protected function requirement(): ?string
    {
        return null;
    }

    /**
     * Replacements of this table's stub, besides the table and the class.
     *
     * @return array<string, string>
     */
    protected function replacements(Repository $config): array
    {
        return [];
    }

    public function handle(Repository $config): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null) {
            return 1;
        }

        if ($module->package() !== null) {
            $this->error(sprintf('Module [%s] comes from the package [%s], which must not be changed. Run "php artisan module:publish %s" first.', $module->id(), $module->package(), $module->id()));

            return 1;
        }

        $requirement = $this->requirement();

        if ($requirement !== null) {
            $this->error($requirement);

            return 1;
        }

        $table = $this->tableKey === null ? $this->table : $config->get($this->tableKey, $this->table);
        $table = is_string($table) && $table !== '' ? $table : $this->table;
        $directory = $module->path('database/migrations');
        $name = 'create_'.$table.'_table';

        if ((glob($directory.DIRECTORY_SEPARATOR.'*_'.$name.'.php') ?: []) !== []) {
            $this->error(sprintf('A migration creating the [%s] table already exists in module [%s].', $table, $module->id()));

            return 1;
        }

        // Anonymous migration classes: every Laravel this line supports has them (8.37+).
        $replace = array_merge([
            'table' => $table,
            'open' => 'return new class extends Migration',
            'close' => ';',
        ], $this->replacements($config));

        $file = $directory.DIRECTORY_SEPARATOR.date('Y_m_d_His').'_'.$name.'.php';
        $stubs = Stubs::fromConfig($config->get('pharaonic.modulator.generation.stubs'), $this->laravel->basePath('stubs'.DIRECTORY_SEPARATOR.'modulator'));

        try {
            Files::put($file, $stubs->render($this->stub, $replace), false);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info(sprintf('Created [%s].', $file));

        return 0;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: string}>
     */
    protected function getArguments()
    {
        return [['module', InputArgument::OPTIONAL, 'The module identity, e.g. Shop or Commerce/Checkout']];
    }
}
