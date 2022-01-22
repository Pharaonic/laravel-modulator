<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DBSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:seed {name}
                            {--force : Force the operation to run when in production}
                            {--class= : The class name of the root seeder [default: "DatabaseSeeder"]}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with records of a module.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $seeders = module_database_path($this->argument('name')) . DIRECTORY_SEPARATOR . 'seeders';

        if (!file_exists($seeders)) {
            $this->error('Seeders directory has not been found.');
            return false;
        }

        // Command
        $command = "db:seed --class=";

        // Options
        $command .= implode('\\\\', [
            '\\App',
            'Modules',
            str_replace('/', '\\\\', $this->argument('name')),
            'database',
            'seeders',
            $this->option('class') ?? 'DatabaseSeeder'
        ]);

        if ($this->option('force')) $command .= ' --force';

        // Calling
        return Artisan::call($command, [], $this->getOutput());
    }
}
