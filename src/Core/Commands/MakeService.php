<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeService extends Command
{
    protected $description = 'Create a new Artisan service class of a module';
    protected $signature = 'module:make:service {module : Module\'s name} {name : Service\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Service}
                            {--pest : Generate an accompanying Pest test for the Service}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE SERVICES DIRECTORY IF NOT FOUND
        if (!file_exists($services = $this->getPath('Services')))
            File::makeDirectory($services, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/service.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Services'), $content);

        // SAVING SERVICE CLASS
        if (file_exists($path = $this->getPath('Services/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Service is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Service created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Services/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
