<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;

final class QueueFailedTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:queue-failed-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the failed queue jobs database table in a module';

    protected string $stub = 'migration.failed-jobs.stub';

    protected ?string $tableKey = 'queue.failed.table';

    protected string $table = 'failed_jobs';

    /**
     * The uuid column, from Laravel 8.
     */
    protected function replacements(Repository $config): array
    {
        return ['uuid' => class_exists('Illuminate\\Queue\\Failed\\DatabaseUuidFailedJobProvider') ? "\$table->string('uuid')->unique();\n            " : ''];
    }
}
