<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class QueueBatchesTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:queue-batches-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the batches database table in a module';

    protected string $stub = 'migration.job-batches.stub';

    protected ?string $tableKey = 'queue.batching.table';

    protected string $table = 'job_batches';
}
