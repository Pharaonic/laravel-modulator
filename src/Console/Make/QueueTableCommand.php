<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class QueueTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:queue-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the queue jobs database table in a module';

    protected string $stub = 'migration.jobs.stub';

    protected ?string $tableKey = 'queue.connections.database.table';

    protected string $table = 'jobs';
}
