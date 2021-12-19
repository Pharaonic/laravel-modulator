<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeRule extends Command
{
    protected $description = 'Create a new validation rule of a module';
    protected $signature = 'module:make:rule {module : Module\'s name} {name : Rule\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE RULES DIRECTORY IF NOT FOUND
        if (!file_exists($rules = $this->getPath('Rules')))
            File::makeDirectory($rules, 0777, true, true);

        // STUB
        $stubContent = file_get_contents(__DIR__ . '/stubs/rule.stub');
        $stubContent = str_replace('{{ class }}', $this->name, $stubContent);
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('Rules'), $stubContent);

        // SAVING SEEDER
        if (file_exists($path = $this->getPath('Rules/' . $this->fullName . '.php'))) {
            $this->error('Rule is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Rule created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
