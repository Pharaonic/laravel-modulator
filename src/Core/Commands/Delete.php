<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\File;
use Pharaonic\Laravel\Modulator\Core\Command;
use Pharaonic\Laravel\Modulator\Core\ModulesFinder;

class Delete extends Command
{
    protected $description = 'Delete a module';
    protected $signature = 'module:delete {module : Module\'s name}';

    public function exec()
    {
        if (!$this->moduleExists()) return;

        File::deleteDirectory($this->getPath(''));
        app(ModulesFinder::class)->build();
        
        $this->info('Module [ ' . $this->module . ' ] has been deleted!');
    }
}
