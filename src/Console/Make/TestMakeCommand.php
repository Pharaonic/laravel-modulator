<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class TestMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:test';

    /**
     * @var string
     */
    protected $description = 'Create a new test class in a module';

    protected string $directory = '@tests';

    protected string $stub = 'test.stub';

    protected function typeOptions(): array
    {
        return [
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test'],
            ['pest', 'p', InputOption::VALUE_NONE, 'Create a Pest test'],
            ['phpunit', null, InputOption::VALUE_NONE, 'Create a PHPUnit test'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->testStub(), $replace, []];
    }
}
