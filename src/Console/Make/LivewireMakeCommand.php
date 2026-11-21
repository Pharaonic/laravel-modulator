<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\LivewireComponents;
use Symfony\Component\Console\Input\InputOption;

final class LivewireMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:livewire';

    /**
     * @var string
     */
    protected $description = 'Create a new Livewire component in a module';

    protected string $directory = 'Http\\Livewire';

    protected string $stub = 'livewire.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['inline', null, InputOption::VALUE_NONE, 'Create a component that renders an inline view'],
        ];
    }

    protected function requirement(): ?string
    {
        return class_exists(LivewireComponents::COMPONENT)
            ? null
            : 'Livewire components need livewire/livewire. Install it with "composer require livewire/livewire".';
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $names = array_map([Str::class, 'kebab'], array_merge($segments, [$class]));
        $replace['view'] = $module->slug().'::livewire.'.implode('.', $names);
        $templates = [];

        if (! $this->option('inline')) {
            $templates[] = [$module->path('resources/views/livewire/'.implode('/', $names).'.blade.php'), 'livewire.view.stub'];
        }

        if ($this->option('test') || $this->option('pest') || $this->option('phpunit')) {
            $legacy = $module->source()->legacy;
            $replace['namespacedComponent'] = $replace['namespace'].'\\'.$class;
            $replace['testNamespace'] = rtrim($module->namespace().($legacy ? '\\tests\\' : '\\Tests\\').'Feature\\Http\\Livewire\\'.implode('\\', $segments), '\\');
            $replace['testClass'] = $class.'Test';
            $stub = str_starts_with($this->testStub(), 'pest') ? 'livewire.pest.stub' : 'livewire.test.stub';
            $templates[] = [$module->path('tests/Feature/Http/Livewire/'.implode('/', array_merge($segments, [$class.'Test'])).'.php'), $stub];
        }

        return [$this->option('inline') ? 'livewire.inline.stub' : $this->stub, $replace, $templates];
    }

    /**
     * The test is written by build(), as a Livewire test.
     */
    protected function companions(Module $module, array $segments): array
    {
        return [];
    }
}
