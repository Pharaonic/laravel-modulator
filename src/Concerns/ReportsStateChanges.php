<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Concerns;

/**
 * What to tell after module:enable or module:disable changed the config file.
 *
 * @internal
 *
 * @mixin \Illuminate\Console\Command
 */
trait ReportsStateChanges
{
    use AdvisesRestart;

    protected function reportStateChange(bool $published): void
    {
        if ($published) {
            $this->line(sprintf('Published the configuration to [%s].', $this->laravel->configPath('pharaonic'.DIRECTORY_SEPARATOR.'modulator.php')));
        }

        $this->line('Takes effect the next time the application boots.');
        $this->adviseRestart();

        if ($this->laravel->configurationIsCached()) {
            $this->warn('The configuration is cached, so the application keeps the old state until you run "php artisan config:cache" again.');
        }
    }
}
