<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class InterfaceMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:interface';

    /**
     * @var string
     */
    protected $description = 'Create a new interface in a module';

    protected string $directory = 'Contracts';

    protected ?string $legacyDirectory = 'Interfaces';

    protected string $stub = 'interface.stub';
}
