<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeAction extends Command
{
    protected $description = 'Create a new Artisan action of a module';
    protected $signature = 'module:make:action {module : Module\'s name} {name : Action\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Action}
                            {--pest : Generate an accompanying Pest test for the Action}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE ACTIONS DIRECTORY IF NOT FOUND
        if (!file_exists($actions = $this->getPath('Actions')))
            File::makeDirectory($actions, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/action.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Actions'), $content);

        // SAVING action
        if (file_exists($path = $this->getPath('Actions/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Action is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Action created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Actions/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
