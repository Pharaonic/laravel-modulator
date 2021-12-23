<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MigrateRefresh extends Command
{
    protected $description = 'Reset and re-run all module migrations.';
    protected $signature = 'module:migrate:refresh {module}
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--seeder= : The class name of the root seeder}
                            {--step= : The number of migrations to be reverted & re-run}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($migrations = module_database_path($this->module, 'migrations')))
            File::makeDirectory($migrations, 0777, true, true);

        // Command
        $command = "migrate:refresh --path=" . $this->getShortPath('database/migrations');

        // Options
        if ($this->option('force')) $command .= ' --force';
        if ($this->option('seed')) $command .= ' --seed';
        if ($seeder = $this->option('seeder')) $command .= ' --seeder=' . $seeder;
        if ($step = $this->option('step')) $command .= ' --step=' . $step;

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
