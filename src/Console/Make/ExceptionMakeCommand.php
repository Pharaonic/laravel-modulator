<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ExceptionMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:exception';

    /**
     * @var string
     */
    protected $description = 'Create a new custom exception class in a module';

    protected string $directory = 'Exceptions';

    protected string $stub = 'exception.stub';

    protected function typeOptions(): array
    {
        return [
            ['render', null, InputOption::VALUE_NONE, 'Add a render method'],
            ['report', null, InputOption::VALUE_NONE, 'Add a report method'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $render = (bool) $this->option('render');
        $report = (bool) $this->option('report');

        if ($render && $report) {
            return ['exception.render-report.stub', $replace, []];
        }

        return [$render ? 'exception.render.stub' : ($report ? 'exception.report.stub' : $this->stub), $replace, []];
    }
}
