<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\State;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\Identity;

final class StatusCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:status
                            {module? : Show one module and why it has its state}';

    /**
     * @var string
     */
    protected $description = 'Show the configured and effective state of the modules';

    public function handle(): int
    {
        $registry = $this->registry();
        $id = $this->argument('module');

        if (! is_string($id) || $id === '') {
            $rows = [];

            foreach ($registry->all() as $module) {
                $rows[] = [
                    $module->id(),
                    $registry->isDisabledInConfig($module->id()) ? 'disabled' : 'enabled',
                    $module->isEnabled() ? 'enabled' : 'disabled',
                    (string) $module->disabledReason(),
                ];
            }

            if ($rows === []) {
                $this->line('No modules found.');
            } else {
                $this->table(['Module', 'Configured', 'Effective', 'Reason'], $rows);
            }

            return 0;
        }

        $module = $this->resolveModule($id);

        if ($module === null) {
            return 1;
        }

        $this->line(sprintf('Module:     %s', $module->id()));
        $this->line(sprintf('Configured: %s', $registry->isDisabledInConfig($module->id()) ? 'disabled (listed in pharaonic.modulator.disabled)' : 'enabled'));
        $this->line(sprintf('Effective:  %s', $module->isEnabled() ? 'enabled' : 'disabled'));

        foreach ($registry->explain($module->id()) as $i => $step) {
            $this->line(sprintf('%s%s: %s', str_repeat('  ', $i + 1), $step['module'], $step['reason']));
        }

        if ($module->parent() !== null) {
            $this->line(sprintf('Parent:     %s', $module->parent()));
        }

        if ($module->dependencies() !== []) {
            $this->line(sprintf('Needs:      %s', implode(', ', $module->dependencies())));
        }

        $dependents = $registry->dependents($module->id());

        if ($dependents !== []) {
            $this->line(sprintf('Needed by:  %s', implode(', ', $dependents)));
        }

        $children = $module->children();

        if ($children !== []) {
            $this->line(sprintf('Children:   %s', implode(', ', array_map(static function (string $child): string {
                return Identity::name($child);
            }, $children))));
        }

        return 0;
    }
}
