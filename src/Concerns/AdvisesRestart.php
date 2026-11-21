<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Concerns;

/**
 * Modules are resolved once, when the application boots, so processes that
 * keep it booted (queue workers, Octane) see a change only after a restart.
 *
 * @internal
 *
 * @mixin \Illuminate\Console\Command
 */
trait AdvisesRestart
{
    protected function adviseRestart(): void
    {
        $commands = ['"php artisan queue:restart"'];

        if (class_exists('Laravel\\Octane\\Octane')) {
            $commands[] = '"php artisan octane:reload"';
        }

        $this->line(sprintf(
            'Running processes keep the old modules until they restart: run %s, and rebuild the route and config caches if you use them.',
            implode(' and ', $commands)
        ));
    }
}
