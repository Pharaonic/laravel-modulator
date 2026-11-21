<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Sources;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;

final class PoolsCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:pools';

    /**
     * @var string
     */
    protected $description = 'List the module pools, highest priority first';

    public function handle(Manifest $manifest): int
    {
        $pools = $manifest->load()['pools'];
        $registry = $this->registry();
        $counts = [];

        foreach ($registry->all() as $module) {
            $counts[$module->pool()] = ($counts[$module->pool()] ?? 0) + 1;
        }

        usort($pools, static function (array $a, array $b): int {
            return [$b['priority'], $a['name']] <=> [$a['priority'], $b['name']];
        });

        $rows = [];

        foreach ($pools as $pool) {
            $rows[] = [
                $pool['name'],
                (string) $pool['priority'],
                $pool['namespace'],
                $pool['path'].(is_dir($pool['path']) ? '' : ' (missing)'),
                $pool['package'] ?? '-',
                $pool['legacy'] ? 'yes' : 'no',
                (string) ($counts[$pool['name']] ?? 0),
            ];
        }

        $this->table(['Pool', 'Priority', 'Namespace', 'Path', 'Package', 'Legacy', 'Active modules'], $rows);

        return 0;
    }
}
