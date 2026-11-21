<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

final class MigrateRefreshCommand extends MigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:migrate:refresh';

    /**
     * @var string
     */
    protected $description = 'Roll back and run again every migration of a module';

    protected string $command = 'migrate:refresh';

    protected array $forward = ['database', 'force', 'seed', 'seeder', 'step'];
}
