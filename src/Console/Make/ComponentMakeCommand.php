<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use Symfony\Component\Console\Input\InputOption;

final class ComponentMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:component';

    /**
     * @var string
     */
    protected $description = 'Create a new view component class in a module';

    protected string $directory = 'View\\Components';

    protected string $stub = 'component.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['inline', null, InputOption::VALUE_NONE, 'Create a component that renders an inline view'],
            ['view', null, InputOption::VALUE_NONE, 'Create an anonymous component with only a view'],
            ['path', null, InputOption::VALUE_REQUIRED, 'The folder of the component view, relative to resources/views', 'components'],
        ];
    }

    protected function generate(Module $module, array $segments, Stubs $stubs, Repository $config): array
    {
        if (! $this->option('view')) {
            return parent::generate($module, $segments, $stubs, $config);
        }

        // An anonymous component: only its view.
        $view = $module->path('resources/views/'.$this->viewPath().implode('/', array_map([Str::class, 'kebab'], $segments)).'.blade.php');

        return [$this->write($view, $stubs->render('component.view.stub', ['class' => (string) end($segments), 'module' => $module->id()]))];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $view = str_replace('/', '.', $this->viewPath()).implode('.', array_map([Str::class, 'kebab'], array_merge($segments, [$class])));
        $replace['view'] = $module->slug().'::'.$view;

        if ($this->option('inline')) {
            return ['component.inline.stub', $replace, []];
        }

        return [$this->stub, $replace, [[$module->path('resources/views/'.str_replace('.', '/', $view).'.blade.php'), 'component.view.stub']]];
    }

    /**
     * The --path folder with a trailing "/", or "".
     */
    private function viewPath(): string
    {
        $path = trim($this->stringOption('path', 'components'), '/.');

        return $path === '' ? '' : $path.'/';
    }
}
