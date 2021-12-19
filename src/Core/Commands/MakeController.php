<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeCommand extends Command
{
    protected $description = 'Create a new controller class of a module';
    protected $signature = 'module:make:controller {module : Module\'s name} {name : Controller\'s name}
                            {--m|model= : The model class that related to the controller}
                            {--force : Create the class even if the controller already exists}
                            {--test : Generate an accompanying PHPUnit test for the Controller}
                            {--pest : Generate an accompanying Pest test for the Controller}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE CONTROLLERS DIRECTORY IF NOT FOUND
        if (!file_exists($controllers = $this->getPath('Http/Controllers')))
            File::makeDirectory($controllers, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/controller.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Http/Controllers'), $content);

        // MODEL
        if ($model = $this->option('model')) {
            $content = str_replace('{{ namespacedModel }}', $model, $content);
            $model = explode('\\', $model);
            $model = array_pop($model);
            $content = str_replace('{{ model }}', $model, $content);
            $content = str_replace('{{ modelVariable }}', Str::camel($model), $content);
        } else {
            $content = str_replace("\nuse {{ namespacedModel }};", '', $content);
            $content = str_replace('\{{ namespacedModel }}', 'int', $content);
            $content = str_replace('{{ model }}', 'int', $content);
            $content = str_replace('{{ modelVariable }}', 'id', $content);
        }

        // SAVING CONTROLLER
        if (file_exists($path = $this->getPath('Http/Controllers/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Controller is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Controller created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }


        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Http/Controllers/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
