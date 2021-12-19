<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeJob extends Command
{
    protected $description = 'Create a new job class of a module';
    protected $signature = 'module:make:job {module : Module\'s name} {name : Job\'s name}
                            {--sync : Indicates that job should be synchronous}
                            {--test : Generate an accompanying PHPUnit test for the Job}
                            {--pest : Generate an accompanying Pest test for the Job}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE JOBS DIRECTORY IF NOT FOUND
        if (!file_exists($jobs = $this->getPath('Jobs')))
            File::makeDirectory($jobs, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/job' . (!$this->option('sync') ? '.queued' : null) . '.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('Jobs'), $content);

        // SAVING JOB
        if (file_exists($path = $this->getPath('Jobs/' . $this->fullName . '.php'))) {
            $this->error('Job is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Job created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }

        // CREATE TEST
        if ($this->option('test') || $this->option('pest')) {
            $command = 'module:make:test ' . $this->module . ' Jobs/' . $this->fullName;
            $command .= $this->option('pest') ? ' --pest' : null;
            Artisan::call($command, [], $this->getOutput());
        }
    }
}
