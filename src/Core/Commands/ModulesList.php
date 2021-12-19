<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;

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

        $modules = array_map(function ($module) {
            return [$module, studlyToSlug($module)];
        }, $modules);

        $this->table(['Name', 'Slug'], $modules);
    }
}
