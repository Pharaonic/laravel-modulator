<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Architecture;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Actions\DiagnoseModules;
use Pharaonic\Laravel\Modulator\Concerns\ReportsDiagnostics;

final class DoctorCommand extends Command
{
    use ReportsDiagnostics;

    /**
     * @var string
     */
    protected $signature = 'module:doctor';

    /**
     * @var string
     */
    protected $description = 'Check discovery, sources, states, dependencies, providers, autoloading and deployment';

    public function handle(DiagnoseModules $diagnose): int
    {
        ['registry' => $registry, 'diagnostics' => $diagnostics] = $diagnose->handle();

        $this->line(sprintf('%d module(s), %d enabled.', count($registry->all()), count($registry->enabled())));

        foreach ($registry->all() as $module) {
            if (! $module->isEnabled()) {
                $this->line(sprintf('  %s: %s', $module->id(), $module->disabledReason()));
            }
        }

        if ($diagnostics === []) {
            $this->info('No problems found.');

            return 0;
        }

        $errors = $this->reportDiagnostics($diagnostics);
        $this->line(sprintf('%d error(s), %d warning(s).', $errors, count($diagnostics) - $errors));

        return $errors > 0 ? 1 : 0;
    }
}
