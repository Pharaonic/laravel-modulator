<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\FilamentComponents;
use Pharaonic\Laravel\Modulator\Support\FilamentSchema;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * module:make:filament-resource: Filament's make:filament-resource, written
 * into a module (app/Filament/Resources, or app/Filament/<PanelId>/Resources),
 * for a model of the module. Filament 3 gets the package's stubs; Filament 4
 * and later run Filament's own command into the module folder.
 */
final class FilamentResourceMakeCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'module:make:filament-resource';

    /**
     * @var string
     */
    protected $description = 'Create a new Filament resource in a module';

    protected string $directory = 'Filament\\Resources';

    protected string $stub = 'filament/resource.stub';

    protected function typeOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_REQUIRED, 'The model, when it is not the module model named after the resource'],
            ['simple', 'S', InputOption::VALUE_NONE, 'Manage the records on one page, with modals'],
            ['view', null, InputOption::VALUE_NONE, 'Add a view page'],
            ['soft-deletes', null, InputOption::VALUE_NONE, 'Show, restore and force-delete soft-deleted records'],
            ['generate', 'G', InputOption::VALUE_NONE, 'Generate the form and table from the model\'s database columns'],
            ['panel', null, InputOption::VALUE_REQUIRED, 'The panel, when it is not the default one: the resource goes to Filament/<PanelId>/Resources'],
        ];
    }

    protected function requirement(): ?string
    {
        return class_exists('Filament\\Resources\\Resource')
            ? null
            : 'Filament resources need filament/filament. Install it with "composer require filament/filament".';
    }

    /**
     * The name is the model ("Product" or "ProductResource"): the class is "<Model>Resource",
     * in the Filament folder of the panel.
     */
    protected function generate(Module $module, array $segments, Stubs $stubs, Repository $config): array
    {
        $model = Str::studly((string) preg_replace('/Resource$/', '', (string) array_pop($segments)));
        [$panel, $default] = $this->panel();
        $directory = $default ? '' : Str::studly($panel).'\\';

        if (FilamentComponents::discovers()) {
            return $this->generateWithFilament($module, $segments, $model, $panel, $directory);
        }

        $this->directory = 'Filament\\'.$directory.'Resources';
        $segments[] = $model.'Resource';

        return parent::generate($module, $segments, $stubs, $config);
    }

    /**
     * Filament 4 and later: Filament's own make:filament-resource, into the
     * module folder the panel discovers, so the resource is what that Filament
     * release writes.
     *
     * @param  list<string>  $segments
     * @return list<string>
     *
     * @throws RuntimeException
     */
    private function generateWithFilament(Module $module, array $segments, string $model, string $panel, string $directory): array
    {
        $class = $this->modelOption();
        $modelNamespace = $module->namespace().'\\Models';
        $modelPath = implode('/', array_merge($segments, [$model]));

        if ($class !== null) {
            $class = ltrim($class, '\\');

            if (class_basename($class) !== $model || $segments !== []) {
                throw new RuntimeException(sprintf('Filament names the resource after its model: name it [%sResource] for --model=%s.', class_basename($class), $class));
            }

            $modelNamespace = Str::beforeLast($class, '\\');
            $modelPath = $model;
        }

        $parameters = [
            'model' => $modelPath,
            '--model-namespace' => $modelNamespace,
            '--resource-namespace' => $module->namespace().'\\Filament\\'.$directory.'Resources',
            '--panel' => $panel,
            '--no-interaction' => true,
        ];

        foreach (['simple', 'view', 'soft-deletes', 'generate'] as $option) {
            if ($this->option($option)) {
                $parameters['--'.$option] = true;
            }
        }

        if ($this->call('make:filament-resource', $parameters) !== 0) {
            throw new RuntimeException('make:filament-resource could not write the resource.');
        }

        return [];
    }

    /**
     * The panel id (--panel, or the default panel) and whether it is the default one.
     *
     * @return array{0: string, 1: bool}
     *
     * @throws RuntimeException
     */
    private function panel(): array
    {
        $id = $this->stringOption('panel', '');
        $filament = $this->laravel->bound('filament') ? $this->laravel->make('filament') : null;
        $panels = is_object($filament) && method_exists($filament, 'getPanels') ? (array) $filament->getPanels() : [];

        if ($panels === []) {
            return [$id, $id === ''];
        }

        foreach ($panels as $panel) {
            if (! is_object($panel) || ! method_exists($panel, 'getId') || ! method_exists($panel, 'isDefault')) {
                continue;
            }

            $default = (bool) $panel->isDefault();
            $panelId = $panel->getId();

            if (is_string($panelId) && ($id === '' ? $default : $panelId === $id)) {
                return [$panelId, $default];
            }
        }

        if ($id === '') {
            throw new RuntimeException('Filament has no default panel: give the panel with --panel=.');
        }

        throw new RuntimeException(sprintf('Filament has no panel [%s].', $id));
    }

    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        // Like Filament: Blog/PostResource is for the model Models\Blog\Post of the module.
        $modelName = $this->modelOption() ?? $module->namespace().'\\Models\\'.implode('\\', array_merge($segments, [(string) preg_replace('/Resource$/', '', $class)]));
        $model = $this->modelReplacements($module, $modelName);
        $plural = Str::pluralStudly($model['model']);
        $simple = (bool) $this->option('simple');
        $view = (bool) $this->option('view') && ! $simple;
        $softDeletes = (bool) $this->option('soft-deletes');

        $resource = $replace['namespace'].'\\'.$class;
        $pagesDirectory = $module->source()->classPath().DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($resource, strlen($module->namespace()) + 1)).DIRECTORY_SEPARATOR.'Pages';

        $names = [
            'listClass' => 'List'.$plural,
            'createClass' => 'Create'.$model['model'],
            'editClass' => 'Edit'.$model['model'],
            'viewClass' => 'View'.$model['model'],
            'manageClass' => 'Manage'.$plural,
        ];

        $pages = $simple
            ? ["'index' => Pages\\{$names['manageClass']}::route('/'),"]
            : array_merge(
                ["'index' => Pages\\{$names['listClass']}::route('/'),", "'create' => Pages\\{$names['createClass']}::route('/create'),"],
                $view ? ["'view' => Pages\\{$names['viewClass']}::route('/{record}'),"] : [],
                ["'edit' => Pages\\{$names['editClass']}::route('/{record}/edit'),"]
            );

        $tableActions = array_merge($view ? ['Tables\\Actions\\ViewAction::make(),'] : [], ['Tables\\Actions\\EditAction::make(),']);
        $bulkActions = ['Tables\\Actions\\DeleteBulkAction::make(),'];
        $editActions = array_merge($view ? ['Actions\\ViewAction::make(),'] : [], ['Actions\\DeleteAction::make(),']);

        if ($simple) {
            $tableActions[] = 'Tables\\Actions\\DeleteAction::make(),';
        }

        if ($softDeletes) {
            $bulkActions = array_merge($bulkActions, ['Tables\\Actions\\ForceDeleteBulkAction::make(),', 'Tables\\Actions\\RestoreBulkAction::make(),']);
            $editActions = array_merge($editActions, ['Actions\\ForceDeleteAction::make(),', 'Actions\\RestoreAction::make(),']);

            if ($simple) {
                $tableActions = array_merge($tableActions, ['Tables\\Actions\\ForceDeleteAction::make(),', 'Tables\\Actions\\RestoreAction::make(),']);
            }
        }

        [$form, $columns] = $this->option('generate') ? $this->schema($model['namespacedModel']) : ['//', '//'];

        $replace = array_merge($replace, $names, [
            'namespacedModel' => $model['namespacedModel'],
            'model' => $model['model'],
            'modelClass' => $model['model'],
            'resource' => $resource,
            'resourceClass' => $class,
            'pagesNamespace' => $resource.'\\Pages',
            'formSchema' => $this->indent($form, 4),
            'tableColumns' => $this->indent($columns, 4),
            'tableFilters' => $this->indent($softDeletes ? 'Tables\\Filters\\TrashedFilter::make(),' : '//', 4),
            'tableActions' => $this->indent(implode("\n", $tableActions), 4),
            'tableBulkActions' => $this->indent(implode("\n", $bulkActions), 5),
            'pages' => $this->indent(implode("\n", $pages), 3),
            'relations' => $simple ? '' : $this->indent("\npublic static function getRelations(): array\n{\n    return [\n        //\n    ];\n}\n", 1),
            'eloquentQuery' => $softDeletes ? $this->indent("\n\npublic static function getEloquentQuery(): Builder\n{\n    return parent::getEloquentQuery()\n        ->withoutGlobalScopes([\n            SoftDeletingScope::class,\n        ]);\n}", 1) : '',
            'editActions' => $this->indent(implode("\n", $editActions), 3),
        ]);

        $templates = [];
        $page = static function (string $class) use ($pagesDirectory): string {
            return $pagesDirectory.DIRECTORY_SEPARATOR.$class.'.php';
        };

        if ($simple) {
            $templates[] = [$page($names['manageClass']), 'filament/resource.manage.stub'];
        } else {
            $templates[] = [$page($names['listClass']), 'filament/resource.list.stub'];
            $templates[] = [$page($names['createClass']), 'filament/resource.create.stub'];
            $templates[] = [$page($names['editClass']), 'filament/resource.edit.stub'];

            if ($view) {
                $templates[] = [$page($names['viewClass']), 'filament/resource.view.stub'];
            }
        }

        return [$this->stub, $replace, $templates];
    }

    /**
     * Filament's generated form and table columns for the model.
     *
     * @return array{0: string, 1: string}
     *
     * @throws RuntimeException
     */
    private function schema(string $model): array
    {
        if (! class_exists($model)) {
            throw new RuntimeException(sprintf('--generate reads the columns of the model [%s], which does not exist yet. Create it first, or leave --generate out.', $model));
        }

        try {
            $schema = new FilamentSchema;

            return [$schema->form($model), $schema->table($model)];
        } catch (Throwable $e) {
            throw new RuntimeException('--generate could not read the model\'s table: '.$e->getMessage(), 0, $e);
        }
    }

    private function indent(string $code, int $level): string
    {
        $prefix = str_repeat('    ', $level);

        return implode("\n", array_map(static function (string $line) use ($prefix): string {
            return $line === '' ? '' : $prefix.$line;
        }, explode("\n", $code)));
    }

    /**
     * The resource pages are written by build().
     */
    protected function companions(Module $module, array $segments): array
    {
        return [];
    }
}
