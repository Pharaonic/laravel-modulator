<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Architecture;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Actions\CheckBoundaries;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Support\Files;

final class CheckCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:check
                            {module? : Only check this module}
                            {--json : Output the violations as JSON}';

    /**
     * @var string
     */
    protected $description = 'Check the class references between modules against their declared dependencies and exposed namespaces';

    public function handle(CheckBoundaries $check): int
    {
        $registry = $this->registry();
        $only = null;
        $argument = $this->argument('module');

        if (is_string($argument) && $argument !== '') {
            $module = $this->resolveModule($argument);

            if ($module === null) {
                return 1;
            }

            $only = $module->id();
        }

        $violations = $check->handle($registry, $only);

        if ($this->option('json')) {
            $this->line((string) json_encode($violations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $violations === [] ? 0 : 1;
        }

        if ($violations === []) {
            $this->info('No boundary violations found.');

            return 0;
        }

        foreach ($violations as $violation) {
            $file = Files::relative($violation['file'], $this->laravel->basePath());
            $this->error(sprintf('  %s:%d', $file, $violation['line']));
            $this->line('  '.$violation['message']);
        }

        $this->line(sprintf('%d violation(s).', count($violations)));

        return 1;
    }
}
