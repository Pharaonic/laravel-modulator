<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class RuleMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:rule';

    /**
     * @var string
     */
    protected $description = 'Create a new validation rule in a module';

    protected string $directory = 'Rules';

    protected string $stub = 'rule.stub';

    protected function typeOptions(): array
    {
        return [
            ['implicit', 'i', InputOption::VALUE_NONE, 'Generate an implicit rule'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        // ValidationRule arrived with Laravel 10; older lines implement Rule / ImplicitRule.
        $legacy = interface_exists('Illuminate\\Contracts\\Validation\\ValidationRule') ? '' : '.legacy';

        return [($this->option('implicit') ? 'rule.implicit' : 'rule').$legacy.'.stub', $replace, []];
    }
}
