<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class SessionTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:session-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the session database table in a module';

    protected string $stub = 'migration.sessions.stub';

    protected ?string $tableKey = 'session.table';

    protected string $table = 'sessions';
}
