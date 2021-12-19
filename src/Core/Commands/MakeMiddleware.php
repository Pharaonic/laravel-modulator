<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeMiddleware extends Command
{
    protected $description = 'Create a new middleware class of a module';
    protected $signature = 'module:make:middleware {module : Module\'s name} {name : Middleware\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Middleware}
                            {--pest : Generate an accompanying Pest test for the Middleware}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE MIDDLEWARES DIRECTORY IF NOT FOUND
        if (!file_exists($mails = $this->getPath('Http/Middleware')))
            File::makeDirectory($mails, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/middleware.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Http/Middleware'), $content);

        // SAVING MIDDLEWARE
        if (file_exists($path = $this->getPath('Http/Middleware/' . $this->fullName . '.php'))) {
            $this->error('Middleware is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Middleware created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Http/Middleware/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
