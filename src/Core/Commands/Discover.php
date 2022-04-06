<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Core\ModulesFinder;

class Discover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:discover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover all the modules.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        app(ModulesFinder::class)->build();
        return true;
    }
}
