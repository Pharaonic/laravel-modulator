<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeSeeder extends Command
{
    protected $description = 'Create a new seeder class of a module';
    protected $signature = 'module:make:seeder {module : Module\'s name} {name : Seeder\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE SEEDERS DIRECTORY IF NOT FOUND
        if (!file_exists($seeders = $this->getPath('database/seeders')))
            File::makeDirectory($seeders, 0777, true, true);

        // SEEDER NAME
        $this->appendName(substr(strtolower($this->name), -6) != 'seeder', 'Seeder');

        // STUB
        $stubContent = file_get_contents(__DIR__ . '/stubs/seeder.stub');
        $stubContent = str_replace('{{ class }}', $this->name, $stubContent);
        $stubContent = str_replace('{{ namespace }}', $this->getNamespace('database/seeders'), $stubContent);

        // SAVING SEEDER
        if (file_exists($path = $this->getPath('database/seeders/' . $this->fullName . '.php'))) {
            $this->error('Seeder is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $stubContent)) {
            $this->info('Seeder created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }
    }
}
