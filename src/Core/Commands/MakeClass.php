<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeClass extends Command
{
    protected $description = 'Create a new Artisan class of a module';
    protected $signature = 'module:make:class {module : Module\'s name} {name : Class\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Class}
                            {--pest : Generate an accompanying Pest test for the Class}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE CLASSES DIRECTORY IF NOT FOUND
        if (!file_exists($classes = $this->getPath('Classes')))
            File::makeDirectory($classes, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/class.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Classes'), $content);

        // SAVING CLASS
        if (file_exists($path = $this->getPath('Classes/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Class is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Class created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Classes/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
