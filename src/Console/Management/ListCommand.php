<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Management;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;

final class ListCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:list';

    /**
     * @var string
     */
    protected $description = 'List every module with its status and source';

    public function handle(): int
    {
        $rows = [];

        foreach ($this->registry()->all() as $module) {
            $rows[] = [
                $module->id(),
                $module->isEnabled() ? 'enabled' : 'disabled',
                $module->pool(),
                $module->package() ?? '-',
                $this->relative($module->path()),
            ];
        }

        if ($rows === []) {
            $this->line('No modules found. Create one with module:make.');

            return 0;
        }

        $this->table(['Module', 'Status', 'Pool', 'Package', 'Path'], $rows);

        return 0;
    }

    private function relative(string $path): string
    {
        $base = rtrim($this->laravel->basePath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
