<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeCommand extends Command
{
    protected $description = 'Create a new Artisan command of a module';
    protected $signature = 'module:make:command {module : Module\'s name} {name : Command\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Command}
                            {--pest : Generate an accompanying Pest test for the Command}
                            {--command=command:name : The terminal command that should be assigned}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE COMMANDS DIRECTORY IF NOT FOUND
        if (!file_exists($commands = $this->getPath('Commands')))
            File::makeDirectory($commands, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/console.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Commands'), $content);
        $content = str_replace('{{ command }}', $this->option('command'), $content);

        // SAVING COMMAND
        if (file_exists($path = $this->getPath('Commands/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Command is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Command created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }


        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Commands/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
