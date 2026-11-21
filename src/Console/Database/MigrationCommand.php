<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * The base of module:migrate, module:migrate:rollback, module:migrate:status,
 * module:migrate:reset and module:migrate:refresh.
 *
 * Each runs Laravel's own command limited to the module's
 * database/migrations (--path). Migrations of every module and of the
 * application share Laravel's migration table and batches: a rollback only
 * reverts this module's migrations of the last batch (or of the last
 * --step migrations), and foreign keys between modules are not followed.
 *
 * @internal
 */
abstract class MigrationCommand extends Command
{
    use ResolvesModules;

    /**
     * Laravel's command, e.g. "migrate:rollback".
     */
    protected string $command = '';

    /**
     * The options forwarded to it: database, force, pretend, seed, step.
     *
     * @var list<string>
     */
    protected array $forward = [];

    public function handle(): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null) {
            return 1;
        }

        $path = $module->path('database/migrations');

        if (! is_dir($path)) {
            $this->line(sprintf('Module [%s] has no database/migrations directory.', $module->id()));

            return 0;
        }

        $parameters = ['--path' => [$path], '--realpath' => true];

        foreach ($this->forward as $option) {
            $value = $this->option($option);

            if ($option !== 'seed' && $value !== null && $value !== false) {
                $parameters['--'.$option] = $value;
            }
        }

        $status = $this->call($this->command, $parameters);

        if ($status === 0 && in_array('seed', $this->forward, true) && $this->option('seed')) {
            $status = $this->call('module:seed', array_filter([
                'module' => $module->id(),
                '--database' => $this->option('database'),
                '--force' => (bool) $this->option('force'),
            ]));
        }

        return $status;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: string}>
     */
    protected function getArguments()
    {
        return [['module', InputArgument::OPTIONAL, 'The module identity, e.g. Shop or Commerce/Checkout']];
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions()
    {
        $definitions = [
            'database' => ['database', null, InputOption::VALUE_REQUIRED, 'The database connection to use'],
            'force' => ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],
            'pretend' => ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],
            'seed' => ['seed', null, InputOption::VALUE_NONE, 'Run the module seeder afterwards'],
            'step' => $this->command === 'migrate'
                ? ['step', null, InputOption::VALUE_NONE, 'Run each migration in its own batch, so it can be rolled back alone']
                : ['step', null, InputOption::VALUE_REQUIRED, 'The number of migrations to roll back'],
        ];

        $options = [];

        foreach ($this->forward as $option) {
            $options[] = $definitions[$option];
        }

        return $options;
    }
}
