<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

/**
 * module:make:enum: a native enum with pharaonic/php-smart-enum's SmartEnum helpers.
 */
final class EnumMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:enum';

    /**
     * @var string
     */
    protected $description = 'Create a new enum in a module';

    protected string $directory = 'Enums';

    protected string $stub = 'enum.stub';

    protected function typeOptions(): array
    {
        return [
            ['string', 's', InputOption::VALUE_NONE, 'Generate a string backed enum'],
            ['int', 'i', InputOption::VALUE_NONE, 'Generate an integer backed enum'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        if (! $this->option('string') && ! $this->option('int')) {
            return [$this->stub, $replace, []];
        }

        $replace['type'] = $this->option('string') ? 'string' : 'int';

        return ['enum.backed.stub', $replace, []];
    }
}
