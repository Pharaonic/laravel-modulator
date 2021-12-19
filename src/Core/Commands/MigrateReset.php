<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Pharaonic\Laravel\Modulator\Core\Command;

class MigrateReset extends Command
{
    protected $description = 'Rollback all database migrations of a module';
    protected $signature = 'module:migrate:reset {module} {--force : Force the operation to run when in production}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        if (!file_exists(module_database_path($this->module, 'migrations'))) {
            $this->error('Migrations directory has not been found.');
            return false;
        }

        // Command
        $command = "migrate:reset --path=" . $this->getShortPath('database/migrations');

        // Options
        if ($this->option('force')) $command .= ' --force';

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
