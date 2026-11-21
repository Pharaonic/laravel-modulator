<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Pharaonic\Laravel\Modulator\Module;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;

final class ControllerMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:controller';

    /**
     * @var string
     */
    protected $description = 'Create a new controller class in a module';

    protected string $directory = 'Http\\Controllers';

    protected string $stub = 'controller.stub';

    protected bool $testable = true;

    protected function typeOptions(): array
    {
        return [
            ['api', null, InputOption::VALUE_NONE, 'Exclude the create and edit methods'],
            ['type', null, InputOption::VALUE_REQUIRED, 'Use the controller.<type>.stub stub'],
            ['invokable', 'i', InputOption::VALUE_NONE, 'Generate a single method, invokable controller'],
            ['model', 'm', InputOption::VALUE_REQUIRED, 'Generate a resource controller for the given model'],
            ['parent', 'p', InputOption::VALUE_REQUIRED, 'Generate a nested resource controller for the given parent model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller'],
            ['requests', 'R', InputOption::VALUE_NONE, 'Generate form requests for the store and update actions'],
        ];
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        $parent = $this->stringOption('parent', '');
        $model = $this->model($class);
        $imports = [];
        $parentParameter = '';
        $modelParameter = '$id';
        $requests = ['Request', 'Request'];

        if ($parent !== '') {
            $parents = $this->modelReplacements($module, $parent);
            $imports[] = $parents['namespacedModel'];
            $parentParameter = $parents['model'].' $'.$parents['modelVariable'];
        }

        if ($model !== null) {
            $models = $this->modelReplacements($module, $model);
            $imports[] = $models['namespacedModel'];
            $modelParameter = $models['model'].' $'.$models['modelVariable'];

            if ($this->option('requests')) {
                $requests = ['Store'.$models['model'].'Request', 'Update'.$models['model'].'Request'];

                foreach ($requests as $request) {
                    $imports[] = $module->namespace().'\\Http\\Requests\\'.$request;
                }
            }
        }

        $replace['parentParameters'] = $parentParameter;
        $replace['parameters'] = $this->join($parentParameter, $modelParameter);
        $replace['storeParameters'] = $this->join($requests[0].' $request', $parentParameter);
        $replace['updateParameters'] = $this->join($requests[1].' $request', $parentParameter, $modelParameter);
        $replace['imports'] = $this->imports($imports);

        return [$this->choose($model !== null || $parent !== ''), $replace, []];
    }

    /**
     * The form requests of --requests, then the matching test.
     */
    protected function companions(Module $module, array $segments): array
    {
        $calls = [];
        $model = $this->option('requests') ? $this->model((string) end($segments)) : null;

        if ($model !== null) {
            $short = class_basename(str_replace('/', '\\', $model));

            foreach (['Store', 'Update'] as $action) {
                $calls[] = ['module:make:request', ['name' => $action.$short.'Request']];
            }
        }

        return array_merge($calls, parent::companions($module, $segments));
    }

    /**
     * The --model, or for a nested resource the model named after the controller.
     */
    private function model(string $class): ?string
    {
        $model = $this->modelOption();

        if ($model === null && $this->stringOption('parent', '') !== '') {
            return (string) preg_replace('/Controller$/', '', $class);
        }

        return $model;
    }

    /**
     * @throws RuntimeException
     */
    private function choose(bool $resource): string
    {
        $type = $this->stringOption('type', '');
        $api = (bool) $this->option('api');

        if ($type !== '') {
            if (preg_match('/^[a-z0-9-]+$/', $type) !== 1) {
                throw new RuntimeException('The controller type must be lowercase letters, digits and dashes.');
            }

            return 'controller.'.$type.'.stub';
        }

        if ($this->option('invokable')) {
            return 'controller.invokable.stub';
        }

        if ($api || $resource || $this->option('resource')) {
            return $api ? 'controller.api.stub' : 'controller.resource.stub';
        }

        return $this->stub;
    }

    private function join(string ...$parameters): string
    {
        return implode(', ', array_filter($parameters, static function (string $parameter): bool {
            return $parameter !== '';
        }));
    }

    /**
     * @param  list<string>  $classes
     */
    private function imports(array $classes): string
    {
        $classes = array_values(array_unique($classes));
        sort($classes, SORT_STRING);

        return implode('', array_map(static function (string $class): string {
            return 'use '.$class.";\n";
        }, $classes));
    }
}
