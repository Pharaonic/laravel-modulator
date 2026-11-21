<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class SeederMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:seeder';

    /**
     * @var string
     */
    protected $description = 'Create a new seeder class in a module';

    protected string $directory = '@seeders';

    protected string $stub = 'seeder.stub';
}
