<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;

final class SeedCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:seed
                            {module? : The module identity, e.g. Shop or Commerce/Checkout}
                            {--class=DatabaseSeeder : The seeder class, relative to the module\'s seeders namespace}
                            {--database= : The database connection to seed}
                            {--force : Force the operation to run when in production}';

    /**
     * @var string
     */
    protected $description = 'Seed the database with the seeder of a module';

    public function handle(): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null) {
            return 1;
        }

        $class = $this->option('class');
        $class = is_string($class) && $class !== '' ? str_replace('/', '\\', $class) : 'DatabaseSeeder';
        $namespace = $module->namespace().($module->source()->legacy ? '\\database\\seeders\\' : '\\Database\\Seeders\\');
        $class = str_starts_with($class, '\\') ? ltrim($class, '\\') : $namespace.$class;

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $class) !== 1 || ! class_exists($class)) {
            $this->error(sprintf('The seeder [%s] does not exist. Create it with "php artisan module:make:seeder %s <Name>".', $class, $module->id()));

            return 1;
        }

        return $this->call('db:seed', array_filter([
            '--class' => $class,
            '--database' => $this->option('database'),
            '--force' => (bool) $this->option('force'),
        ]));
    }
}
