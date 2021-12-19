<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeEvent extends Command
{
    protected $description = 'Create a new event class of a module';
    protected $signature = 'module:make:event {module : Module\'s name} {name : Event\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE EVENTS DIRECTORY IF NOT FOUND
        if (!file_exists($events = $this->getPath('Events')))
            File::makeDirectory($events, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/event.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Events'), $content);

        // SAVING EVENT
        if (file_exists($path = $this->getPath('Events/' . $this->fullName . '.php'))) {
            $this->error('Event is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Event created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
