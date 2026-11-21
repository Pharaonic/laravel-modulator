<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ClassMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:class';

    /**
     * @var string
     */
    protected $description = 'Create a new class in a module';

    protected string $directory = '';

    protected ?string $legacyDirectory = 'Classes';

    protected string $stub = 'class.stub';

    protected function typeOptions(): array
    {
        return [
            ['invokable', 'i', InputOption::VALUE_NONE, 'Generate a single method, invokable class'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->option('invokable') ? 'class.invokable.stub' : 'class.stub', $replace, []];
    }
}
