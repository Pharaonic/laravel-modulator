<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class CastMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:cast';

    /**
     * @var string
     */
    protected $description = 'Create a new custom Eloquent cast class in a module';

    protected string $directory = 'Casts';

    protected string $stub = 'cast.stub';

    protected function typeOptions(): array
    {
        return [
            ['inbound', null, InputOption::VALUE_NONE, 'Generate an inbound cast class'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->option('inbound') ? 'cast.inbound.stub' : 'cast.stub', $replace, []];
    }
}
