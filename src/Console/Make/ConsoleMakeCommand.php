<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ConsoleMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:command';

    /**
     * @var string
     */
    protected $description = 'Create a new Artisan command in a module';

    protected string $directory = 'Console\\Commands';

    protected ?string $legacyDirectory = 'Commands';

    protected string $stub = 'command.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['command', null, InputOption::VALUE_REQUIRED, 'The terminal command name'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $replace['command'] = $this->stringOption('command', $module->slug().':'.Str::kebab((string) preg_replace('/Command$/', '', $class)));

        return [$this->stub, $replace, []];
    }
}
