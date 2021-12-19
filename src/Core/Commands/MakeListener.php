<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeListener extends Command
{
    protected $description = 'Create a new event listener class of a module';
    protected $signature = 'module:make:listener {module : Module\'s name} {name : Listener\'s name}
                            {--e|event= : The event class being listened for}
                            {--queued : Indicates the event listener should be queued}
                            {--test : Generate an accompanying PHPUnit test for the Listener}
                            {--pest : Generate an accompanying Pest test for the Listener}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE LISTENERS DIRECTORY IF NOT FOUND
        if (!file_exists($listeners = $this->getPath('Listeners')))
            File::makeDirectory($listeners, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/listener.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Listeners'), $content);

        // QUEUED
        if ($this->option('queued')) {
            $content = str_replace('{{ interface }}', ' implements ShouldQueue', $content);
            $content = str_replace('{{ trait }}', 'use InteractsWithQueue;', $content);
        } else {
            $content = str_replace('{{ interface }}', '', $content);
            $content = str_replace("    {{ trait }}\n\n", '', $content);
        }

        // EVENT
        if ($event = $this->option('event')) {
            $content = str_replace('{{ modelNamespace }}', $event, $content);
            $content = str_replace("\n" . $event, "\nuse " . $event . ';', $content);
            $event = explode('\\', $event);
            $event = array_pop($event);
            $content = str_replace('{{ model }}', $event, $content);
            $content = str_replace('{{ modelVariable }}', Str::camel($event), $content);
        } else {
            $content = str_replace('{{ modelNamespace }}', 'object', $content);
            $content = str_replace("\nobject", '', $content);
            $content = str_replace('{{ model }} ', '', $content);
            $content = str_replace('{{ modelVariable }}', 'event', $content);
        }

        // SAVING LISTENER
        if (file_exists($path = $this->getPath('Listeners/' . $this->fullName . '.php'))) {
            $this->error('Listener is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Listener created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Listeners/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
