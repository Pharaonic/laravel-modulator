<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Architecture;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Module;

final class GraphCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:graph
                            {--format=text : text or json}';

    /**
     * @var string
     */
    protected $description = 'Show the module hierarchy and dependencies';

    public function handle(): int
    {
        $registry = $this->registry();
        $modules = $registry->all();
        $format = $this->option('format');

        if ($format === 'json') {
            $edges = [];
            $nodes = [];

            foreach ($modules as $module) {
                $nodes[] = [
                    'id' => $module->id(),
                    'enabled' => $module->isEnabled(),
                    'reason' => $module->disabledReason(),
                    'pool' => $module->pool(),
                    'package' => $module->package(),
                    'parent' => $module->parent(),
                    'children' => $module->children(),
                    'dependencies' => $module->dependencies(),
                ];

                if ($module->parent() !== null) {
                    $edges[] = ['from' => $module->id(), 'to' => $module->parent(), 'type' => 'parent'];
                }

                foreach ($module->dependencies() as $dependency) {
                    $edges[] = ['from' => $module->id(), 'to' => $dependency, 'type' => 'dependency', 'missing' => ! isset($modules[$dependency])];
                }
            }

            $this->line((string) json_encode(['modules' => $nodes, 'edges' => $edges], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        if ($format !== 'text') {
            $this->error('The format must be "text" or "json".');

            return 1;
        }

        if ($modules === []) {
            $this->line('No modules found.');

            return 0;
        }

        foreach ($modules as $module) {
            if ($module->parent() === null) {
                $this->tree($modules, $module, '', '');
            }
        }

        return 0;
    }

    /**
     * @param  array<string, Module>  $modules
     */
    private function tree(array $modules, Module $module, string $prefix, string $branch): void
    {
        $state = $module->isEnabled() ? '<info>enabled</info>' : '<comment>disabled</comment>';
        $line = $prefix.$branch.$module->id().' ['.$state.']';

        if ($module->dependencies() !== []) {
            $line .= ' -> needs '.implode(', ', array_map(static function (string $dependency) use ($modules): string {
                return isset($modules[$dependency]) ? $dependency : $dependency.' (missing)';
            }, $module->dependencies()));
        }

        $this->line($line);
        $children = $module->children();
        $childPrefix = $prefix.($branch === '' ? '' : ($branch === '└─ ' ? '   ' : '│  '));

        foreach ($children as $i => $child) {
            if (isset($modules[$child])) {
                $this->tree($modules, $modules[$child], $childPrefix, $i === count($children) - 1 ? '└─ ' : '├─ ');
            }
        }
    }
}
