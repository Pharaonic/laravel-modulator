<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Discovery;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Concerns\ReportsDiagnostics;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Throwable;

/**
 * The deployment build: rebuilds the manifest (sources and class maps) after
 * "composer install" / "composer dump-autoload", and fails on any error, so a
 * broken module tree never reaches production silently.
 */
final class CacheCommand extends Command
{
    use ReportsDiagnostics;

    /**
     * @var string
     */
    protected $signature = 'module:cache';

    /**
     * @var string
     */
    protected $description = 'Build the module manifest and class maps for production, failing on any error';

    public function handle(Manifest $manifest, Repository $config): int
    {
        try {
            $data = $manifest->rebuild();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $registry = ModuleRegistry::fromManifest($data, $config->get('pharaonic.modulator.disabled', []));
        $errors = $this->reportDiagnostics($registry->diagnostics());

        if ($errors > 0) {
            $manifest->clear();
            $this->error(sprintf('Module cache not written: %d error(s). Fix them and run module:cache again.', $errors));

            return 1;
        }

        $classes = 0;

        foreach ($data['classmap'] as $map) {
            $classes += count($map);
        }

        $this->info(sprintf('Modules cached: %d module(s), %d class(es).', count($registry->all()), $classes));

        return 0;
    }
}
