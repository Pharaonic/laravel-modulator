<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ListenerMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:listener';

    /**
     * @var string
     */
    protected $description = 'Create a new event listener class in a module';

    protected string $directory = 'Listeners';

    protected string $stub = 'listener.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['event', 'e', InputOption::VALUE_REQUIRED, 'The event class being listened for'],
            ['queued', null, InputOption::VALUE_NONE, 'Make the listener queued'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $replace['eventParameter'] = 'object $event';
        $event = $this->stringOption('event', '');

        if ($event !== '') {
            $event = str_contains($event, '\\') ? ltrim($event, '\\') : $module->namespace().'\\Events\\'.$event;
            $replace['imports'] = 'use '.$event.";\n\n";
            $replace['eventParameter'] = class_basename($event).' $event';
        }

        return [$this->option('queued') ? 'listener.queued.stub' : $this->stub, $replace, []];
    }
}
