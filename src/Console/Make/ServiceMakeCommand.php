<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class ServiceMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:service';

    /**
     * @var string
     */
    protected $description = 'Create a new service class in a module';

    protected string $directory = 'Services';

    protected string $stub = 'service.stub';
}
