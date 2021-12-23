<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($migrations = module_database_path($this->module, 'migrations')))
            File::makeDirectory($migrations, 0777, true, true);

        // Command
        $command = 'make:migration ' . $this->argument('migration') . ' --path=' . $this->getShortPath('database/migrations');

        // Options
        if ($create = $this->option('create')) $command .= ' --create=' . $create;
        if ($table = $this->option('table')) $command .= ' --table=' . $table;

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
