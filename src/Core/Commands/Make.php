<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Make extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $path = module_path($name);

        $this->line($name);
        $this->info($name);
        $this->comment($name);
        $this->question($name);
        $this->warn($name);
        $this->error($name);
        $this->alert($name);

        $this->newLine(2);

        $this->table(
            ['Name', 'Email'],
            [
                [
                    'asd', 'ss'
                ]
            ]
        );

        return 0;
    }
}
