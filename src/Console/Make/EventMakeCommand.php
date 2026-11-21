<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

final class EventMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:event';

    /**
     * @var string
     */
    protected $description = 'Create a new event class in a module';

    protected string $directory = 'Events';

    protected string $stub = 'event.stub';
}
