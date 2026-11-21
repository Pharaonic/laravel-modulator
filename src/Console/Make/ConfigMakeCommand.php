<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\Stubs;

final class ConfigMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:config';

    /**
     * @var string
     */
    protected $description = 'Create a new configuration file in a module';

    protected string $directory = '';

    protected string $stub = 'config.stub';

    protected function segments(string $name): ?array
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1 ? [$name] : null;
    }

    protected function generate(Module $module, array $segments, Stubs $stubs, Repository $config): array
    {
        $replace = ['name' => $segments[0], 'slug' => $module->slug(), 'module' => $module->id()];

        return [$this->write($module->path('config/'.$segments[0].'.php'), $stubs->render($this->stub, $replace))];
    }
}
