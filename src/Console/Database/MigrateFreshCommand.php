<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

use Illuminate\Console\Command;

/**
 * 1.x ran "migrate:fresh --path=<module>", which drops every table of the
 * database, not only the module's. The command now refuses and explains.
 */
final class MigrateFreshCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:migrate:fresh
                            {module? : Ignored}
                            {--drop-views : Ignored}
                            {--drop-types : Ignored}
                            {--force : Ignored}
                            {--seed : Ignored}
                            {--seeder= : Ignored}
                            {--schema-path= : Ignored}
                            {--step= : Ignored}';

    /**
     * @var string
     */
    protected $description = 'Removed: Laravel\'s migrate:fresh drops every table of the database, not only a module\'s';

    public function handle(): int
    {
        $this->error('module:migrate:fresh was removed: Laravel\'s migrate:fresh drops every table of the database, whatever --path says, so it cannot be limited to one module.');
        $this->line('Use "php artisan module:migrate:refresh <module>" to roll back and run again the migrations of one module,');
        $this->line('or "php artisan migrate:fresh" to rebuild the whole database on purpose.');

        return 1;
    }
}
