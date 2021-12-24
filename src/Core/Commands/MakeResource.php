<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Jsonable\Json;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeResource extends Command
{
    protected $description = 'Create a new resource of a module';
    protected $signature = 'module:make:resource {module : Module\'s name} {name : Resource\'s name}
                            {--c|collection : Create a resource collection.}
                            {--j|json : Create a new jsonable form request class}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE RESOURCES DIRECTORY IF NOT FOUND
        if (!file_exists($resources = $this->getPath('Http/Resources')))
            File::makeDirectory($resources, 0777, true, true);

        // Jsonable
        if ($this->option('json')) {
            if (class_exists(Json::class)) {
                return Artisan::call('jsonable:resource App/Modules/' . $this->argument('module') . '/Http/Resources/' . $this->argument('name'), [], $this->getOutput());
            } else {
                return $this->warn('Jsonable Package has not been found.');
            }
        }

        // STUB
        $stubContent = file_get_contents(__DIR__ . '/stubs/resource' . ($this->option('collection') ? '-collection' : null) . '.stub');
        $stubContent = str_replace('{{ class }}', $this->name, $stubContent);
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Http/Resources'), $stubContent);

        // SAVING TEST
        if (file_exists($path = $this->getPath('Http/Resources/' . $this->fullName . '.php'))) {
            $this->error('Resource is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Resource' . ($this->option('collection') ? ' collection' : null) . ' created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
