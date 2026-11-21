<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Sources;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\Identity;
use Pharaonic\Laravel\Modulator\Support\ModuleLock;
use RuntimeException;

final class SourceCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:source
                            {module : The module identity, e.g. Shop or Commerce/Checkout}';

    /**
     * @var string
     */
    protected $description = 'Show where a module comes from, its shadowed copies and why its source won';

    public function handle(): int
    {
        $registry = $this->registry();
        $id = $this->argument('module');
        $id = is_string($id) ? $id : '';

        try {
            Identity::assert($id);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $sources = $registry->sources($id);
        $module = $registry->find($id);

        if ($sources === []) {
            $this->error(sprintf('Module [%s] does not exist. Run module:list to see the modules.', $id));

            return 1;
        }

        $rows = [];

        foreach ($sources as $source) {
            $active = $module !== null && $module->source() === $source;
            $rows[] = [
                $active ? 'active' : ($module === null ? 'unresolved' : 'shadowed'),
                $source->pool,
                (string) $source->priority,
                $source->package ?? '-',
                $source->path,
            ];
        }

        $this->table(['Source', 'Pool', 'Priority', 'Package', 'Path'], $rows);

        if ($module === null) {
            $this->error(sprintf('No source of [%s] is active: two pools provide it with the same priority. Run module:doctor.', $id));

            return 1;
        }

        $this->line(sprintf('Status:  %s', $module->isEnabled() ? 'enabled' : 'disabled. '.$module->disabledReason()));

        if (count($sources) > 1) {
            $this->line(sprintf(
                'Reason:  pool [%s] has the highest priority (%d); the other copies are ignored, even when the module is disabled.',
                $module->pool(),
                $module->source()->priority
            ));
        } else {
            $this->line(sprintf('Reason:  pool [%s] is the only source.', $module->pool()));
        }

        try {
            $lock = ModuleLock::read($module->source()->path);
        } catch (RuntimeException $e) {
            $lock = null;
            $this->warn($e->getMessage());
        }

        if ($lock !== null) {
            $this->line(sprintf('Published: from %s %s (%s). module:doctor reports what the package changed since.', $lock['package'], $lock['version'] ?? '(unknown version)', ModuleLock::FILE));
        }

        return 0;
    }
}
