<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Symfony\Component\Console\Input\InputOption;

final class ModelMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:model';

    /**
     * @var string
     */
    protected $description = 'Create a new Eloquent model class in a module';

    protected string $directory = 'Models';

    protected string $stub = 'model.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Also generate a migration, seeder, factory, policy, resource controller and form requests'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Also generate a controller'],
            ['factory', 'f', InputOption::VALUE_NONE, 'Also generate a factory'],
            ['migration', 'm', InputOption::VALUE_NONE, 'Also generate a migration'],
            ['morph-pivot', null, InputOption::VALUE_NONE, 'Make the model a custom polymorphic intermediate table model'],
            ['policy', null, InputOption::VALUE_NONE, 'Also generate a policy'],
            ['seed', 's', InputOption::VALUE_NONE, 'Also generate a seeder'],
            ['pivot', 'p', InputOption::VALUE_NONE, 'Make the model a custom intermediate table model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Make the generated controller a resource controller'],
            ['api', null, InputOption::VALUE_NONE, 'Make the generated controller an API resource controller'],
            ['requests', 'R', InputOption::VALUE_NONE, 'Also generate form requests, used by the resource controller'],
        ];
    }

    protected function requirement(): ?string
    {
        return $this->option('pivot') && $this->option('morph-pivot') ? 'The pivot and morph-pivot options cannot be combined.' : null;
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        if ($this->option('pivot')) {
            return ['model.pivot.stub', $replace, []];
        }

        if ($this->option('morph-pivot')) {
            return ['model.morph-pivot.stub', $replace, []];
        }

        // HasFactory arrived with Laravel 8.
        $factory = ($this->option('factory') || $this->option('all')) && trait_exists('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');

        return [$factory ? 'model.factory.stub' : $this->stub, $replace, []];
    }

    /**
     * The migration, factory, seeder, policy and controller the options ask
     * for, then the matching test.
     */
    protected function companions(Module $module, array $segments): array
    {
        $all = (bool) $this->option('all');
        $name = implode('/', $segments);
        $base = (string) end($segments);
        $model = '\\'.$module->namespace().'\\Models\\'.str_replace('/', '\\', $name);
        $calls = [];

        if ($all || $this->option('migration')) {
            $table = Str::snake(Str::pluralStudly($base));
            $calls[] = ['module:make:migration', ['name' => 'create_'.$table.'_table', '--create' => $table]];
        }

        if ($all || $this->option('factory')) {
            $calls[] = ['module:make:factory', ['name' => $base.'Factory', '--model' => $model]];
        }

        if ($all || $this->option('seed')) {
            $calls[] = ['module:make:seeder', ['name' => $base.'Seeder']];
        }

        if ($all || $this->option('policy')) {
            $calls[] = ['module:make:policy', ['name' => $base.'Policy', '--model' => $model]];
        }

        $resource = $all || $this->option('resource') || $this->option('api') || $this->option('requests');

        if ($resource || $this->option('controller')) {
            $calls[] = ['module:make:controller', array_filter([
                'name' => $name.'Controller',
                '--model' => $resource ? $model : null,
                '--api' => (bool) $this->option('api'),
                '--requests' => $all || $this->option('requests'),
            ])];
        }

        return array_merge($calls, parent::companions($module, $segments));
    }
}
