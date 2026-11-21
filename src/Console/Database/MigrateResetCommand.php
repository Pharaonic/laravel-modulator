<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

final class MigrateResetCommand extends MigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:migrate:reset';

    /**
     * @var string
     */
    protected $description = 'Roll back every migration of a module';

    protected string $command = 'migrate:reset';

    protected array $forward = ['database', 'force', 'pretend'];
}
