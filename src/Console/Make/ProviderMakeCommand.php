<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class ProviderMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:provider';

    /**
     * @var string
     */
    protected $description = 'Create a new service provider class in a module';

    protected string $directory = 'Providers';

    protected string $stub = 'provider.stub';
}
