<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeTrait extends Command
{
    protected $description = 'Create a new Artisan trait of a module';
    protected $signature = 'module:make:trait {module : Module\'s name} {name : Trait\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Trait}
                            {--pest : Generate an accompanying Pest test for the Trait}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE TRAITS DIRECTORY IF NOT FOUND
        if (!file_exists($traits = $this->getPath('Traits')))
            File::makeDirectory($traits, 0777, true, true);

        $content = str_replace('{{ trait }}', $this->name, file_get_contents(__DIR__ . '/stubs/trait.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Traits'), $content);

        // SAVING TRAIT
        if (file_exists($path = $this->getPath('Traits/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Trait is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Trait created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Traits/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
