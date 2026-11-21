<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\State;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\Context;
use Pharaonic\Laravel\Modulator\Support\Identity;

final class CurrentCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:current';

    /**
     * @var string
     */
    protected $description = 'Show the module selected with module:use';

    public function handle(): int
    {
        $id = $this->moduleContext()->get();

        if ($id === null) {
            $this->line('No module is selected. Select one with module:use.');

            return 0;
        }

        $module = Identity::isValid($id) ? $this->registry()->find($id) : null;

        if ($module === null) {
            $this->error(sprintf('The selected module [%s] no longer exists. Select another one with module:use, or clear it with module:use --clear.', $id));

            return 1;
        }

        $this->line($module->id());

        if (! $module->isEnabled()) {
            $this->warn('The module is disabled: '.$module->disabledReason());
        }

        if (! Context::isTrusted($this->input->isInteractive(), $this->laravel->environment() === 'production')) {
            $this->warn('This console is not interactive, in CI or in production: commands ignore the selection here.');
        }

        return 0;
    }
}
