<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeMigration extends Command
{
    protected $description = 'Create a new migration file of a module.';
    protected $signature = 'module:make:migration {module} {migration}
                            {--create= : The table to be created}
                            {--table= : The table to migrate}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        if (!file_exists(module_database_path($this->module, 'migrations'))) {
            $this->error('Migrations directory has not been found.');
            return false;
        }

        // Command
        $command = 'make:migration ' . $this->argument('migration') . ' --path=' . $this->getShortPath('database/migrations');

        // Options
        if ($create = $this->option('create')) $command .= ' --create=' . $create;
        if ($table = $this->option('table')) $command .= ' --table=' . $table;

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
