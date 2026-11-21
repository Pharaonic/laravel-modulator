<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ResourceMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:resource';

    /**
     * @var string
     */
    protected $description = 'Create a new resource in a module';

    protected string $directory = 'Http\\Resources';

    protected string $stub = 'resource.stub';

    protected function typeOptions(): array
    {
        return [
            ['collection', 'c', InputOption::VALUE_NONE, 'Create a resource collection'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->option('collection') || str_ends_with($class, 'Collection') ? 'resource.collection.stub' : $this->stub, $replace, []];
    }
}
