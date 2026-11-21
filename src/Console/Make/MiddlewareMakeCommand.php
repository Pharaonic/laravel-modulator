<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class MiddlewareMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:middleware';

    /**
     * @var string
     */
    protected $description = 'Create a new HTTP middleware class in a module';

    protected string $directory = 'Http\\Middleware';

    protected string $stub = 'middleware.stub';

    protected bool $testable = true;
}
