<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;
use Pharaonic\Laravel\Modulator\Core\StreamOutput as CoreStreamOutput;

class MigrateReset extends Command
{
    protected $description = 'Rollback all database migrations of a module';
    protected $signature = 'module:migrate:reset {module} {--force : Force the operation to run when in production}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CHECK IF MIGRATIONS NOT EXISTS
        if (!file_exists($path = module_database_path($this->module, 'migrations')))
            File::makeDirectory($path, 0777, true, true);

        $files = array_diff(scandir($path), array('.', '..'));
        arsort($files);
     
        // Calling
        $stream = fopen('php://stdout', 'w+');
        $output = new CoreStreamOutput($stream, function ($line) {
            return strpos($line, 'not found') === false;
        });

        foreach ($files as $file) {
            $command = "migrate:reset --realpath --path=" . $path . DIRECTORY_SEPARATOR . $file;
            if ($this->option('force')) $command .= ' --force';

            Artisan::call($command, [], $output);
        }

        return 0;
    }
}
