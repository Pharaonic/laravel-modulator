<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Make;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\Support\Files;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * The base of the module:make:* generators, one per make:* generator of Laravel.
 *
 * Laravel's make:* commands are bound to the application namespace, and their
 * output changes between Laravel versions; these generators write the same
 * code into a module on every supported Laravel line, from stubs that can be
 * replaced (`pharaonic.modulator.generation.stubs`). A generator or option
 * that needs a newer Laravel or PHP says so instead of writing code that
 * cannot run.
 *
 * A generator sets its directory and stub, and may add options, a
 * requirement, replacements (build()) and companion commands (companions()).
 *
 * @internal
 */
abstract class GeneratorCommand extends Command
{
    use ResolvesModules;

    /**
     * Names PHP does not accept as class names.
     */
    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
        'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof',
        'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public',
        'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
        'var', 'while', 'xor', 'yield', 'self', 'parent', 'bool', 'false', 'float', 'int', 'iterable', 'mixed',
        'never', 'null', 'object', 'string', 'true', 'void',
    ];

    /**
     * The namespace of the class, relative to the module ("Http\Controllers"),
     * or "@factories", "@seeders" or "@tests" for the folders outside app/.
     */
    protected string $directory = '';

    /**
     * The same in a legacy (1.x) module, when it differs.
     */
    protected ?string $legacyDirectory = null;

    /**
     * The stub file, e.g. "controller.stub".
     */
    protected string $stub = '';

    /**
     * Whether --test, --pest and --phpunit write a matching test.
     */
    protected bool $testable = false;

    public function handle(Manifest $manifest, Repository $config): int
    {
        $first = $this->argument('module');
        $second = $this->argument('name');
        [$moduleArgument, $name] = is_string($second) && $second !== '' ? [$first, $second] : [null, $first];

        if (! is_string($name) || $name === '') {
            $this->error('Give the name.');

            return 1;
        }

        $module = $this->resolveModule($moduleArgument);

        if ($module === null || ! $this->isWritable($module)) {
            return 1;
        }

        $segments = str_contains($name, '..') ? null : $this->segments($name);

        if ($segments === null) {
            $this->error(sprintf('[%s] is not a valid name.', $name));

            return 1;
        }

        try {
            $requirement = $this->requirement();

            if ($requirement !== null) {
                throw new RuntimeException($requirement);
            }

            if ($this->getDefinition()->hasOption('pest') && $this->option('pest') && ! $this->pestInstalled()) {
                throw new RuntimeException('--pest needs Pest, and vendor/bin/pest does not exist. Install pestphp/pest, or leave --pest out for a PHPUnit test.');
            }

            $created = $this->generate($module, $segments, Stubs::fromConfig($config->get('pharaonic.modulator.generation.stubs')), $config);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        foreach ($created as $file) {
            $this->info(sprintf('Created [%s].', $this->relative($file)));
        }

        $status = 0;

        foreach ($this->companions($module, $segments) as [$command, $parameters]) {
            $status = max($status, $this->call($command, ['module' => $module->id()] + $parameters));
        }

        $this->refresh($manifest);

        return $status;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: string}>
     */
    protected function getArguments()
    {
        return [
            ['module', InputArgument::REQUIRED, 'The module identity (or the name, to use the module selected with module:use)'],
            ['name', InputArgument::OPTIONAL, 'The name, e.g. ProductController or Admin/ProductController'],
        ];
    }

    /**
     * @return list<array<int, mixed>>
     */
    protected function getOptions()
    {
        $options = $this->typeOptions();

        if ($this->testable) {
            $options[] = ['test', null, InputOption::VALUE_NONE, 'Generate an accompanying test'];
            $options[] = ['pest', null, InputOption::VALUE_NONE, 'Generate an accompanying Pest test'];
            $options[] = ['phpunit', null, InputOption::VALUE_NONE, 'Generate an accompanying PHPUnit test'];
        }

        $options[] = ['force', null, InputOption::VALUE_NONE, 'Overwrite the file when it already exists'];

        return $options;
    }

    /**
     * The options of this generator: [name, shortcut, mode, description, default].
     *
     * @return list<array<int, mixed>>
     */
    protected function typeOptions(): array
    {
        return [];
    }

    /**
     * Why this generator cannot run here (a Laravel or PHP version), or null.
     */
    protected function requirement(): ?string
    {
        return null;
    }

    /**
     * The validated name segments ("Admin/ProductController"), or null.
     *
     * @return list<string>|null
     */
    protected function segments(string $name): ?array
    {
        $segments = preg_split('/[\\\\\/]+/', trim($name, '\\/')) ?: [];

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1 || in_array(strtolower($segment), self::RESERVED, true)) {
                return null;
            }
        }

        return $segments === [] ? null : $segments;
    }

    /**
     * Write the class (and the templates build() asks for).
     *
     * @param  list<string>  $segments
     * @return list<string> The files written.
     *
     * @throws RuntimeException
     */
    protected function generate(Module $module, array $segments, Stubs $stubs, Repository $config): array
    {
        [$namespace, $directory] = $this->location($module);
        $class = (string) array_pop($segments);

        if ($segments !== []) {
            $namespace = ltrim($namespace.'\\'.implode('\\', $segments), '\\');
            $directory .= DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
        }

        [$stub, $replace, $templates] = $this->build($module, $class, $segments, [
            'namespace' => $namespace,
            'class' => $class,
            'rootNamespace' => $module->namespace(),
            'module' => $module->id(),
            'slug' => $module->slug(),
            'imports' => '',
        ], $config);

        $created = [$this->write($directory.DIRECTORY_SEPARATOR.$class.'.php', $stubs->render($stub, $replace))];

        // Templates are kept when they exist, unless --force.
        foreach ($templates as [$file, $template]) {
            if (! is_file($file) || $this->option('force')) {
                $created[] = $this->write($file, $stubs->render($template, $replace));
            }
        }

        return $created;
    }

    /**
     * The stub, its replacements, and the templates to write next to the class.
     *
     * @param  list<string>  $segments  The sub-namespaces before the class.
     * @param  array<string, string>  $replace
     * @return array{0: string, 1: array<string, string>, 2: list<array{0: string, 1: string}>}
     */
    protected function build(Module $module, string $class, array $segments, array $replace, Repository $config): array
    {
        return [$this->stub, $replace, []];
    }

    /**
     * Commands to run after the class is written: [command, parameters]; the
     * module argument is added. By default, the matching test.
     *
     * @param  list<string>  $segments
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function companions(Module $module, array $segments): array
    {
        if (! $this->testable || (! $this->option('test') && ! $this->option('pest') && ! $this->option('phpunit'))) {
            return [];
        }

        $directory = str_replace('\\', '/', $this->directoryFor($module));

        return [['module:make:test', array_filter([
            'name' => ltrim($directory.'/'.implode('/', $segments).'Test', '/'),
            '--pest' => (bool) $this->option('pest'),
            '--phpunit' => (bool) $this->option('phpunit'),
            '--force' => (bool) $this->option('force'),
        ])]];
    }

    /**
     * @throws RuntimeException
     */
    protected function write(string $file, string $contents): string
    {
        Files::put($file, $contents, (bool) $this->option('force'));

        return $file;
    }

    /**
     * "pest.stub", "test.stub" or "test.unit.stub": --phpunit and --pest
     * decide, otherwise Pest when it is installed.
     */
    protected function testStub(): string
    {
        $definition = $this->getDefinition();
        $pest = $definition->hasOption('pest') && $this->option('pest');
        $phpunit = $definition->hasOption('phpunit') && $this->option('phpunit');

        if (! $phpunit && ($pest || $this->pestInstalled())) {
            return 'pest.stub';
        }

        return $definition->hasOption('unit') && $this->option('unit') ? 'test.unit.stub' : 'test.stub';
    }

    protected function pestInstalled(): bool
    {
        return is_file($this->laravel->basePath('vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'pest'));
    }

    protected function stringOption(string $name, string $default): string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    protected function modelOption(): ?string
    {
        if (! $this->getDefinition()->hasOption('model')) {
            return null;
        }

        $model = $this->option('model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    /**
     * "Product" is a model of the module; "App\Models\User" is used as given.
     *
     * @return array<string, string>
     */
    protected function modelReplacements(Module $module, ?string $model): array
    {
        if ($model === null) {
            return [];
        }

        $model = str_replace('/', '\\', $model);
        $class = str_contains($model, '\\') ? ltrim($model, '\\') : $module->namespace().'\\Models\\'.$model;

        return [
            'namespacedModel' => $class,
            'model' => class_basename($class),
            'modelVariable' => Str::camel(class_basename($class)),
        ];
    }

    /**
     * The user model of the --guard option (or the default guard).
     *
     * @return array<string, string>
     */
    protected function userReplacements(Repository $config): array
    {
        $guard = $this->getDefinition()->hasOption('guard') ? $this->option('guard') : null;
        $guard = is_string($guard) && $guard !== '' ? $guard : $config->get('auth.defaults.guard');
        $provider = is_string($guard) ? $config->get('auth.guards.'.$guard.'.provider') : null;
        $model = is_string($provider) ? $config->get('auth.providers.'.$provider.'.model') : null;
        $model = $model ?? $config->get('auth.providers.users.model');
        $model = is_string($model) && $model !== '' ? ltrim($model, '\\') : (class_exists('App\\Models\\User') ? 'App\\Models\\User' : 'App\\User');

        return ['namespacedUserModel' => $model, 'userModel' => class_basename($model)];
    }

    /**
     * The namespace and directory of this generator in the module.
     *
     * @return array{0: string, 1: string}
     */
    protected function location(Module $module): array
    {
        $legacy = $module->source()->legacy;
        $directory = $this->directoryFor($module);
        $namespace = $module->namespace();

        switch ($directory) {
            case '@factories':
                return [$namespace.($legacy ? '\\database\\factories' : '\\Database\\Factories'), $module->path('database/factories')];
            case '@seeders':
                return [$namespace.($legacy ? '\\database\\seeders' : '\\Database\\Seeders'), $module->path('database/seeders')];
            case '@tests':
                $kind = $this->getDefinition()->hasOption('unit') && $this->option('unit') ? 'Unit' : 'Feature';

                return [$namespace.($legacy ? '\\tests\\' : '\\Tests\\').$kind, $module->path('tests/'.$kind)];
        }

        $root = $module->source()->classPath();

        if ($directory === '') {
            return [$namespace, $root];
        }

        return [$namespace.'\\'.$directory, $root.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $directory)];
    }

    private function directoryFor(Module $module): string
    {
        return $module->source()->legacy && $this->legacyDirectory !== null ? $this->legacyDirectory : $this->directory;
    }

    private function isWritable(Module $module): bool
    {
        if ($module->package() === null) {
            return true;
        }

        $this->error(sprintf(
            'Module [%s] comes from the package [%s], which must not be changed. Run "php artisan module:publish %s" to get a local copy, then generate into it.',
            $module->id(),
            $module->package(),
            $module->id()
        ));

        return false;
    }

    private function refresh(Manifest $manifest): void
    {
        if (! is_file($manifest->path())) {
            return;
        }

        try {
            $manifest->rebuild();
        } catch (RuntimeException $e) {
            $this->warn('The module manifest could not be rebuilt: '.$e->getMessage().' Run module:cache.');
        }
    }

    private function relative(string $path): string
    {
        $base = rtrim($this->laravel->basePath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
