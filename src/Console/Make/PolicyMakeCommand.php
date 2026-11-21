<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class PolicyMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:policy';

    /**
     * @var string
     */
    protected $description = 'Create a new policy class in a module';

    protected string $directory = 'Policies';

    protected string $stub = 'policy.stub';

    protected function typeOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model the policy applies to'],
            ['guard', 'g', InputOption::VALUE_REQUIRED, 'The guard whose user model the policy uses'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $model = $this->modelOption();
        $replace += $this->userReplacements($config) + $this->modelReplacements($module, $model);

        return [$model === null ? 'policy.plain.stub' : $this->stub, $replace, []];
    }
}
