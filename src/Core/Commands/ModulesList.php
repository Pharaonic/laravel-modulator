<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModulesList extends Command
{
    protected $description = 'Display all modules info.';
    protected $signature = 'module:list';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (empty($modules = modules())) {
            $this->error('There are no modules.');
            return false;
        }

        for ($i = 0; $i < count($modules); $i++) {
            if (file_exists(module_path($modules[$i], 'Providers'))) {
                $modules[$i] = $modules[$i];
            } else {
                $subModules = [];
                foreach(File::directories(module_path($modules[$i])) as $module) {
                    $subModules[] = $modules[$i] . '/' . basename($module);
                }
                
                array_splice($modules, $i, 1, ...$subModules);
            }
        }

        $modules = array_map(function ($module) {
            return [$module, studlyToSlug($module)];
        }, $modules);

        $this->table(['Name', 'Slug'], $modules);
    }
}
