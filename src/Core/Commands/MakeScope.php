<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeScope extends Command
{
    protected $description = 'Create a new Artisan scope of a module';
    protected $signature = 'module:make:scope {module : Module\'s name} {name : Scope\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Scope}
                            {--pest : Generate an accompanying Pest test for the Scope}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE SCOPES DIRECTORY IF NOT FOUND
        if (!file_exists($scopes = $this->getPath('Scopes')))
            File::makeDirectory($scopes, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/scope.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Scopes'), $content);

        // SAVING scope
        if (file_exists($path = $this->getPath('Scopes/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Scope is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Scope created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Scopes/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
