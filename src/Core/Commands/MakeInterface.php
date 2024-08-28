<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeInterface extends Command
{
    protected $description = 'Create a new Artisan interface of a module';
    protected $signature = 'module:make:interface {module : Module\'s name} {name : Interface\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Interface}
                            {--pest : Generate an accompanying Pest test for the Interface}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE INTERFACES DIRECTORY IF NOT FOUND
        if (!file_exists($interfaces = $this->getPath('Interfaces')))
            File::makeDirectory($interfaces, 0777, true, true);

        $content = str_replace('{{ interface }}', $this->name, file_get_contents(__DIR__ . '/stubs/interface.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Interfaces'), $content);

        // SAVING INTERFACE
        if (file_exists($path = $this->getPath('Interfaces/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Interface is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Interface created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Interfaces/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
