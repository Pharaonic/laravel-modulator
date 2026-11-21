<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class NotificationsTableCommand extends TableMigrationCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:notifications-table';

    /**
     * @var string
     */
    protected $description = 'Create a migration for the notifications table in a module';

    protected string $stub = 'migration.notifications.stub';

    protected string $table = 'notifications';
}
