<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Database;

final class MigrateRollbackCommand extends MigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:migrate:rollback';

    /**
     * @var string
     */
    protected $description = 'Roll back the last migrations of a module';

    protected string $command = 'migrate:rollback';

    protected array $forward = ['database', 'force', 'pretend', 'step'];
}
