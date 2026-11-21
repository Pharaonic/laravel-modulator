<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class JobMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:job';

    /**
     * @var string
     */
    protected $description = 'Create a new job class in a module';

    protected string $directory = 'Jobs';

    protected string $stub = 'job.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['sync', null, InputOption::VALUE_NONE, 'Generate a synchronous job'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->option('sync') ? 'job.sync.stub' : $this->stub, $replace, []];
    }
}
