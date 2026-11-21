<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class RequestMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:request';

    /**
     * @var string
     */
    protected $description = 'Create a new form request class in a module';

    protected string $directory = 'Http\\Requests';

    protected string $stub = 'request.stub';
}
