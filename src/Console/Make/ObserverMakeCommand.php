<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ObserverMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:observer';

    /**
     * @var string
     */
    protected $description = 'Create a new observer class in a module';

    protected string $directory = 'Observers';

    protected string $stub = 'observer.stub';

    protected function typeOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the observer applies to'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $model = $this->modelOption();

        return [$model === null ? 'observer.plain.stub' : $this->stub, $replace + $this->modelReplacements($module, $model), []];
    }
}
