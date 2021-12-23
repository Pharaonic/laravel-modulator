<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MigrateRollback extends Command
{
    protected $description = 'Rollback the last database migration of a module.';
    protected $signature = 'module:migrate:rollback {module}
                            {--force : Force the operation to run when in production}
                            {--step= : The number of migrations to be reverted}';


    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($migrations = module_database_path($this->module, 'migrations')))
            File::makeDirectory($migrations, 0777, true, true);

        // Command
        $command = "migrate:rollback --path=" . $this->getShortPath('database/migrations');

        // Options
        if ($this->option('force')) $command .= ' --force';
        if ($step = $this->option('step')) $command .= ' --step=' . $step;

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
