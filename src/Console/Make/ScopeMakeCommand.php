<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class ScopeMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:scope';

    /**
     * @var string
     */
    protected $description = 'Create a new scope class in a module';

    protected string $directory = 'Models\\Scopes';

    protected ?string $legacyDirectory = 'Scopes';

    protected string $stub = 'scope.stub';
}
