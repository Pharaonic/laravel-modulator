<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeFactory extends Command
{
    protected $description = 'Create a new model factory of a module';
    protected $signature = 'module:make:factory {module : Module\'s name} {name : Factory\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE FACTORIES DIRECTORY IF NOT FOUND
        if (!file_exists($factories = $this->getPath('database/factories')))
            File::makeDirectory($factories, 0777, true, true);

        // FACTORY NAME
        $this->appendName(substr(strtolower($this->name), -7) != 'factory', 'Factory');

        // STUB
        $stubContent = file_get_contents(__DIR__ . '/stubs/factory.stub');
        $stubContent = str_replace('{{ factory }}', $this->name, $stubContent);
        $stubContent = str_replace('{{ factoryNamespace }}', $this->getNamespace('database/factories'), $stubContent);

        // SAVING FACTORY
        if (file_exists($path = $this->getPath('database/factories/' . $this->fullName . '.php'))) {
            $this->error('Factory is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Factory created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
