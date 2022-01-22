<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;

class MakeComponent extends Command
{
    protected $description = 'Create a new view component class of a module';
    protected $signature = 'module:make:component {module : Module\'s name} {name : Component\'s name}
                            {--force : Create the class even if the component already exists}
                            {--inline : Create a component that renders an inline view}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        // CREATE COMPONENTS DIRECTORY IF NOT FOUND
        if (!file_exists($components = $this->getPath('View/Components')))
            File::makeDirectory($components, 0777, true, true);

        $content = str_replace('{{ class }}', $this->name, file_get_contents(__DIR__ . '/stubs/component.stub'));
        $content = str_replace('{{ namespace }}', $this->getNamespace('View/Components'), $content);

        if ($this->option('inline')) {
            $content = str_replace(
                '{{ view }}',
                '<<<\'blade\'' . PHP_EOL . '<div>' . PHP_EOL . '<!-- Pharaonic - Moamen Eltouny -->' . PHP_EOL . '</div>' . PHP_EOL . 'blade',
                $content
            );
        } else {
            $sPath = array_map(function ($dir) {
                return studlyToSlug($dir);
            }, explode('/', $this->fullName));

            $view = $this->slug . '::components.' . implode('.', $sPath);
            $content = str_replace('{{ view }}', 'view(\'' . $view . '\')', $content);

        }

        // SAVING COMMAND
        if (file_exists($path = $this->getPath('View/Components/' . $this->fullName . '.php')) && !$this->option('force')) {
            $this->error('Component is already exists!');
            return false;
        }

        if (!File::isDirectory($dir = dirname($path)))
            File::makeDirectory($dir, 0755, true, true);

        if (File::put($path, $content)) {
            $this->info('Component created successfully.');
        } else {
            $this->warn('There is something wrong.');
        }


        // CREATE VIEW OF NON-INLINE
        if (!$this->option('inline')) {
            $view = module_resource_path($this->module, 'views/components/' . implode('/', $sPath)  . '.blade.php');

            if (!File::isDirectory($dir = dirname($view)))
                File::makeDirectory($dir, 0755, true, true);

            if (File::put($view, '<div>' . PHP_EOL . '    <!-- Pharaonic - Moamen Eltouny -->' . PHP_EOL . '</div>')) {
                $this->info('Component-View created successfully.');
            } else {
                $this->warn('There is something wrong.');
            }
        }
    }
}
