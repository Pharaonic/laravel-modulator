<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

final class ViewMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:view';

    /**
     * @var string
     */
    protected $description = 'Create a new view in a module';

    protected string $directory = '';

    protected string $stub = 'view.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['extension', null, InputOption::VALUE_REQUIRED, 'The extension of the view file', 'blade.php'],
        ];
    }

    protected function segments(string $name): ?array
    {
        $segments = preg_split('/[.\/\\\\]+/', trim($name, './\\')) ?: [];

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1) {
                return null;
            }
        }

        return $segments === [] ? null : $segments;
    }

    protected function generate(Module $module, array $segments, Stubs $stubs, Repository $config): array
    {
        $extension = ltrim($this->stringOption('extension', 'blade.php'), '.');

        if (preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $extension) !== 1) {
            throw new RuntimeException('The view extension must be letters and digits, e.g. blade.php.');
        }

        $replace = ['view' => $module->slug().'::'.implode('.', $segments), 'module' => $module->id(), 'class' => '', 'namespace' => ''];
        $created = [$this->write($module->path('resources/views/'.implode('/', $segments).'.'.$extension), $stubs->render($this->stub, $replace))];

        if ($this->option('test') || $this->option('pest') || $this->option('phpunit')) {
            $class = implode('', array_map([Str::class, 'studly'], $segments)).'Test';
            $replace['namespace'] = $module->namespace().($module->source()->legacy ? '\\tests\\' : '\\Tests\\').'Feature\\View';
            $replace['class'] = $class;
            $stub = $this->testStub() === 'pest.stub' ? 'view.pest.stub' : 'view.test.stub';
            $created[] = $this->write($module->path('tests/Feature/View/'.$class.'.php'), $stubs->render($stub, $replace));
        }

        return $created;
    }

    protected function companions(Module $module, array $segments): array
    {
        return [];
    }
}
