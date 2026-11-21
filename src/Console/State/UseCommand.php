<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\State;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use RuntimeException;

final class UseCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:use
                            {module? : The module identity, e.g. Shop or Commerce/Checkout}
                            {--clear : Forget the selected module}';

    /**
     * @var string
     */
    protected $description = 'Select the module that module commands target when none is given (your console only)';

    public function handle(): int
    {
        if ($this->option('clear')) {
            $this->moduleContext()->clear();
            $this->info('No module is selected now.');

            return 0;
        }

        $id = $this->argument('module');

        if (! is_string($id) || $id === '') {
            $this->error('Give the module identity, e.g. "module:use Shop", or use --clear.');

            return 1;
        }

        $module = $this->resolveModule($id);

        if ($module === null) {
            return 1;
        }

        try {
            $this->moduleContext()->set($module->id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info(sprintf('Module [%s] is selected for your console.', $module->id()));
        $this->line('Commands that take a module use it when you leave the module out, on interactive consoles only (never in CI or production).');

        if (! $module->isEnabled()) {
            $this->warn('The module is disabled: '.$module->disabledReason());
        }

        return 0;
    }
}
