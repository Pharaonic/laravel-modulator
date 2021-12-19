<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeProvider extends Command
{
    protected $description = 'Create a new service provider class of a module';
    protected $signature = 'module:make:provider {module : Module\'s name} {name : Provider\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE PROVIDERS DIRECTORY IF NOT FOUND
        if (!file_exists($requests = $this->getPath('Providers')))
            File::makeDirectory($requests, 0777, true, true);

        // PROVIDER NAME
        $this->appendName(substr(strtolower($this->name), -8) != 'provider', 'Provider');

        // STUB
        $stubContent = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/provider.stub'));
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Providers'), $stubContent);

        // SAVING REQUEST
        if (file_exists($path = $this->getPath('Providers/' . $this->fullName . '.php'))) {
            $this->error('Provider is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Provider created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
