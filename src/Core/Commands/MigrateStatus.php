<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MigrateStatus extends Command
{
    protected $description = 'Show the status of each migration of a module';
    protected $signature = 'module:migrate:status {module}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($migrations = module_database_path($this->module, 'migrations')))
            File::makeDirectory($migrations, 0777, true, true);

        return Artisan::call("migrate:status --path=" . $this->getShortPath('database/migrations'), [], $this->getOutput());
    }
}
