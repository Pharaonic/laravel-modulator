<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Development;

use Illuminate\Console\Command;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Symfony\Component\Process\Process;

final class TestCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:test
                            {module? : The module identity, e.g. Shop or Commerce/Checkout}
                            {--f|filter= : Only run the tests matching this pattern}
                            {--l|list : List the tests without running them}
                            {--s|stop-on-failure : Stop on the first failure}
                            {--coverage : Collect code coverage}
                            {--profile : List the slowest tests (Pest only)}
                            {--p|parallel : Run the tests in parallel (Pest only)}';

    /**
     * @var string
     */
    protected $description = 'Run the tests of a module with Pest or PHPUnit';

    public function handle(): int
    {
        $module = $this->resolveModule($this->argument('module'));

        if ($module === null) {
            return 1;
        }

        $tests = $module->path('tests');

        if (! is_dir($tests)) {
            $this->error(sprintf('Module [%s] has no tests directory.', $module->id()));

            return 1;
        }

        $pest = $this->laravel->basePath('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest');
        $phpunit = $this->laravel->basePath('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'phpunit');
        $binary = is_file($pest) ? $pest : (is_file($phpunit) ? $phpunit : null);

        if ($binary === null) {
            $this->error('Neither vendor/bin/pest nor vendor/bin/phpunit exists. Install Pest or PHPUnit.');

            return 1;
        }

        $isPest = $binary === $pest;

        if (! $isPest && ($this->option('profile') || $this->option('parallel'))) {
            $this->error('--profile and --parallel need Pest.');

            return 1;
        }

        $arguments = [PHP_BINARY, $binary, $tests];
        $filter = $this->option('filter');

        if ($this->option('list')) {
            $arguments[] = '--list-tests';
        } else {
            if (is_string($filter) && $filter !== '') {
                $arguments[] = '--filter='.$filter;
            }

            foreach (['stop-on-failure' => '--stop-on-failure', 'profile' => '--profile', 'parallel' => '--parallel'] as $option => $flag) {
                if ($this->option($option)) {
                    $arguments[] = $flag;
                }
            }

            if ($this->option('coverage')) {
                $arguments[] = $isPest ? '--coverage' : '--coverage-text';
            }
        }

        $process = new Process($arguments, $this->laravel->basePath(), null, null, null);

        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }
}
