<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MigrateFresh extends Command
{
    protected $description = 'Drop all tables then re-run migrations of a module.';
    protected $signature = 'module:migrate:fresh {module}
                            {--drop-views : Drop all tables and views}
                            {--drop-types : Drop all tables and types (Postgres only)}
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--seeder= : The class name of the root seeder}
                            {--schema-path= : The path to a schema dump file}
                            {--step= : Force the migrations to be run so they can be rolled back individually}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($migrations = module_database_path($this->module, 'migrations')))
            File::makeDirectory($migrations, 0777, true, true);

        // Command
        $command = "migrate:fresh --path=" . $this->getShortPath('database/migrations');

        // Options
        if ($this->option('drop-views')) $command .= ' --drop-views';
        if ($this->option('drop-types')) $command .= ' --drop-types';
        if ($this->option('force')) $command .= ' --force';
        if ($this->option('seed')) $command .= ' --seed';
        if ($seeder = $this->option('seeder')) $command .= ' --seeder=' . $seeder;
        if ($step = $this->option('step')) $command .= ' --step=' . $step;
        if ($schema = $this->option('schema-path')) $command .= ' --schema-path=' . $schema;

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
