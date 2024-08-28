<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeEnum extends Command
{
    protected $description = 'Create a new Artisan enum of a module';
    protected $signature = 'module:make:enum {module : Module\'s name} {name : Enum\'s name}
                            {--test : Generate an accompanying PHPUnit test for the Enum}
                            {--pest : Generate an accompanying Pest test for the Enum}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE ENUMS DIRECTORY IF NOT FOUND
        if (!file_exists($enums = $this->getPath('Enums')))
            File::makeDirectory($enums, 0777, true, true);

        $content = str_replace('{{ enum }}', $this->name, file_get_contents(__DIR__ . '/stubs/enum.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Enums'), $content);

        // SAVING ENUM
        if (file_exists($path = $this->getPath('Enums/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Enum is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Enum created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Enums/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
