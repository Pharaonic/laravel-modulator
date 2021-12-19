<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeCast extends Command
{
    protected $description = 'Create a new custom Eloquent cast class of a module';
    protected $signature = 'module:make:cast {module : Module\'s name} {name : Cast\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE CASTS DIRECTORY IF NOT FOUND
        if (!file_exists($casts = $this->getPath('Casts')))
            File::makeDirectory($casts, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/cast.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Casts'), $content);

        // SAVING CAST
        if (file_exists($path = $this->getPath('Casts/' . $this->fullName . '.php'))) {
            $this->error('Cast is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Cast created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
