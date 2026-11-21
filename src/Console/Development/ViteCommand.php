<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Development;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\ViteEntries;

/**
 * module:vite: the Vite entry points of the enabled modules, as JSON, for the
 * application's vite.config.js (resources/vite/modulator.js reads it).
 */
final class ViteCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:vite
                            {module? : Only the entries of this module}';

    /**
     * @var string
     */
    protected $description = 'List the Vite entry points of the enabled modules as JSON';

    public function handle(Repository $config): int
    {
        $modules = $this->registry()->enabled();
        $argument = $this->argument('module');

        if (is_string($argument) && $argument !== '') {
            $module = $this->resolveModule($argument);

            if ($module === null) {
                return 1;
            }

            $modules = [$module];
        }

        $entries = ViteEntries::configured($config);
        $paths = [];

        foreach ($modules as $module) {
            $paths = array_merge($paths, ViteEntries::existing($module, $entries, $this->laravel->basePath()));
        }

        $this->line((string) json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
