<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;

final class ChannelMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:channel';

    /**
     * @var string
     */
    protected $description = 'Create a new broadcast channel class in a module';

    protected string $directory = 'Broadcasting';

    protected string $stub = 'channel.stub';

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->stub, $replace + $this->userReplacements($config), []];
    }
}
