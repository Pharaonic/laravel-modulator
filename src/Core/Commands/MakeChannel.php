<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeChannel extends Command
{
    protected $description = 'Create a new channel class of a module';
    protected $signature = 'module:make:channel {module : Module\'s name} {name : Channel\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE BROADCASTING DIRECTORY IF NOT FOUND
        if (!file_exists($broadcasting = $this->getPath('Broadcasting')))
            File::makeDirectory($broadcasting, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/broadcasting.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Broadcasting'), $content);
        $model  = config('auth.providers.' . config('auth.guards.' . config('auth.defaults.guard') . '.provider') . '.model');
        $content = str_replace('{{ namespacedModel }}', $model, $content);

        $model = explode('\\', $model);
        $model = $model[count($model) - 1];
        $content = str_replace('{{ model }}', $model, $content);
        $content = str_replace('{{ modelVariable }}', Str::camel($model), $content);

        // SAVING BROADCASTING
        if (file_exists($path = $this->getPath('Broadcasting/' . $this->fullName . '.php'))) {
            $this->error('Channel is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Channel created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
