<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Discovery;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Concerns\ReportsDiagnostics;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Throwable;

final class DiscoverCommand extends Command
{
    use ReportsDiagnostics;

    /**
     * @var string
     */
    protected $signature = 'module:discover';

    /**
     * @var string
     */
    protected $description = 'Discover the modules of every pool and rebuild the module manifest';

    public function handle(Manifest $manifest, Repository $config): int
    {
        try {
            $data = $manifest->rebuild();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $registry = ModuleRegistry::fromManifest($data, $config->get('pharaonic.modulator.disabled', []));
        $rows = [];

        foreach ($registry->all() as $module) {
            $rows[] = [$module->id(), $module->pool(), $module->isEnabled() ? 'enabled' : 'disabled'];
        }

        if ($rows === []) {
            $this->line('No modules found.');
        } else {
            $this->table(['Module', 'Pool', 'Status'], $rows);
        }

        $this->reportDiagnostics($registry->diagnostics());
        $this->info('Module manifest written to '.$manifest->path().'.');

        return 0;
    }
}
