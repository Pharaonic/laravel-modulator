<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class JobMiddlewareMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:job-middleware';

    /**
     * @var string
     */
    protected $description = 'Create a new job middleware class in a module';

    protected string $directory = 'Jobs\\Middleware';

    protected string $stub = 'job-middleware.stub';

    protected bool $testable = true;
}
