<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeModel extends Command
{
    protected $description = 'Create a new Eloquent model class of a module';
    protected $signature = 'module:make:model {module : Module\'s name} {name : Model\'s name}
                            {--a|all : Create a new migration, fatcory, seeder and policy files for the model}
                            {--m|migration : Create a new migration file for the model}
                            {--f|factory : Create a new factory for the model}
                            {--s|seed : Create a new seeder for the model}
                            {--p|policy : Create a new policy for the model}
                            {--force : Create the class even if the model already exists}
                            {--test : Generate an accompanying PHPUnit test for the Model}
                            {--pest : Generate an accompanying Pest test for the Model}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE MODELS DIRECTORY IF NOT FOUND
        if (!file_exists($controllers = $this->getPath('Models')))
            File::makeDirectory($controllers, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/model.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Models'), $content);

        // SAVING MODEL
        if (file_exists($path = $this->getPath('Models/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Model is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Model created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE MIGRATION
        if ($this->option('migration') || $this->option('all')) {
            $command = 'module:make:migration ' . $this->module . ' create_' . Str::snake(Str::plural($this->name)) . '_table';
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }

        // CREATE FACTORY
        if ($this->option('factory') || $this->option('all')) {
            $command = 'module:make:factory ' . $this->module . ' ' . $this->fullName;
            Artisan::call($command, [], $this->getOutput());
        }

        // CREATE SEEDER
        if ($this->option('seed') || $this->option('all')) {
            $command = 'module:make:seeder ' . $this->module . ' ' . $this->fullName;
            Artisan::call($command, [], $this->getOutput());
        }

        // CREATE POLICY
        if ($this->option('policy') || $this->option('all')) {
            $command = 'module:make:policy ' . $this->module . ' ' . $this->fullName;
            Artisan::call($command, [], $this->getOutput());
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Models/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
