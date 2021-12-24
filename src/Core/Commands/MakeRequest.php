<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Jsonable\Json;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeRequest extends Command
{
    protected $description = 'Create a new form request class of a module';
    protected $signature = 'module:make:request {module : Module\'s name} {name : Request\'s name}
                            {--j|json : Create a new jsonable form request class}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE REQUESTS DIRECTORY IF NOT FOUND
        if (!file_exists($requests = $this->getPath('Http/Requests')))
            File::makeDirectory($requests, 0777, true, true);

        // Jsonable
        if ($this->option('json')) {
            if (class_exists(Json::class)) {
                return Artisan::call('jsonable:request App/Modules/' . $this->argument('module') . '/Http/Requests/' . $this->argument('name'), [], $this->getOutput());
            } else {
                return $this->warn('Jsonable Package has not been found.');
            }
        }

        // STUB
        $stubContent = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/request.stub'));
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Http\Requests'), $stubContent);

        // SAVING REQUEST
        if (file_exists($path = $this->getPath('Http/Requests/' . $this->fullName . '.php'))) {
            $this->error('Request is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Request created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
