<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class TraitMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:trait';

    /**
     * @var string
     */
    protected $description = 'Create a new trait in a module';

    protected string $directory = 'Concerns';

    protected ?string $legacyDirectory = 'Traits';

    protected string $stub = 'trait.stub';
}
