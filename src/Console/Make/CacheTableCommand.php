<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;

final class CacheTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:cache-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the cache database table in a module';

    protected string $stub = 'migration.cache.stub';

    protected ?string $tableKey = 'cache.stores.database.table';

    protected string $table = 'cache';

    /**
     * The lock table, from Laravel 8.
     */
    protected function replacements(Repository $config): array
    {
        if (! class_exists('Illuminate\\Cache\\DatabaseLock')) {
            return ['locks' => '', 'dropLocks' => ''];
        }

        $locks = $config->get('cache.stores.database.lock_table', 'cache_locks');
        $locks = is_string($locks) && $locks !== '' ? $locks : 'cache_locks';

        return [
            'locks' => "\n\n        Schema::create('".$locks."', function (Blueprint \$table) {\n            \$table->string('key')->primary();\n            \$table->string('owner');\n            \$table->integer('expiration');\n        });",
            'dropLocks' => "\n        Schema::dropIfExists('".$locks."');",
        ];
    }
}
