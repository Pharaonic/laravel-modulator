<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

final class MigrateStatusCommand extends MigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:migrate:status';

    /**
     * @var string
     */
    protected $description = 'Show the status of the migrations of a module';

    protected string $command = 'migrate:status';

    protected array $forward = ['database'];
}
