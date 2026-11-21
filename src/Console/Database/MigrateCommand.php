<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

final class MigrateCommand extends MigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:migrate';

    /**
     * @var string
     */
    protected $description = 'Run the migrations of a module';

    protected string $command = 'migrate';

    protected array $forward = ['database', 'force', 'pretend', 'seed', 'step'];
}
