<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeObserver extends Command
{
    protected $description = 'Create a new observer class of a module';
    protected $signature = 'module:make:observer {module : Module\'s name} {name : Observer\'s name} {--model= : The model that the observer applies to}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE OBSERVERS DIRECTORY IF NOT FOUND
        if (!file_exists($observers = $this->getPath('Observers')))
            File::makeDirectory($observers, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/observer' . ($this->option('model') ? null : '.plain') . '.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Observers'), $content);

        if ($this->option('model')) {
            $model = $this->option('model');
            $content = str_replace('{{ namespacedModel }}', $model, $content);

            $model = explode('\\', $model);
            $model = $model[count($model) - 1];
            $content = str_replace('{{ model }}', $model, $content);
            $content = str_replace('{{ modelVariable }}', Str::camel($model), $content);
        }

        // SAVING OBSERVER
        if (file_exists($path = $this->getPath('Observers/' . $this->fullName . '.php'))) {
            $this->error('Observer is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Observer created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
