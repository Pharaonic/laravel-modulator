<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class FactoryMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:factory';

    /**
     * @var string
     */
    protected $description = 'Create a new model factory in a module';

    protected string $directory = '@factories';

    protected string $stub = 'factory.stub';

    protected function typeOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the factory is for'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        // Laravel 6 and 7 define factories as closures.
        $stub = class_exists('Illuminate\\Database\\Eloquent\\Factories\\Factory') ? $this->stub : 'factory.legacy.stub';
        $model = $this->modelOption() ?? (string) preg_replace('/Factory$/', '', $class);

        return [$stub, $replace + $this->modelReplacements($module, $model), []];
    }
}
