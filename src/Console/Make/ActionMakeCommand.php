<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class ActionMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:action';

    /**
     * @var string
     */
    protected $description = 'Create a new action class in a module';

    protected string $directory = 'Actions';

    protected string $stub = 'action.stub';
}
