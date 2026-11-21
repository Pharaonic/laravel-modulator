<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Files;
use Pharaonic\Laravel\Modulator\Support\Identity;
use Pharaonic\Laravel\Modulator\Support\Stubs;
use RuntimeException;
use Throwable;

/**
 * Creates a module in the generation pool. It is built in a private
 * workspace, then moved into place, so the pool never holds half a module.
 *
 * @internal
 */
final class MakeModule
{
    /**
     * Stub => file in the new module.
     */
    private const FILES = [
        'module/provider.stub' => 'app/Providers/{name}ServiceProvider.php',
        'module/routes-web.stub' => 'routes/web.php',
        'module/routes-api.stub' => 'routes/api.php',
        'module/seeder.stub' => 'database/seeders/DatabaseSeeder.php',
    ];

    /**
     * Empty directories of the new module.
     */
    private const DIRECTORIES = ['database/factories', 'database/migrations', 'resources/views', 'resources/lang', 'tests'];

    public function __construct(
        private Application $app,
        private Manifest $manifest,
        private ModuleRegistry $registry,
        private Repository $config
    ) {
    }

    /**
     * @return array{path: string, namespace: string}
     *
     * @throws RuntimeException When the module cannot be created; nothing is written.
     */
    public function handle(string $id, bool $plain = false): array
    {
        $poolName = $this->config->get('pharaonic.modulator.generation.pool', 'local');
        $pool = is_string($poolName) ? $this->manifest->pool($poolName) : null;

        if ($pool === null || $pool['package'] !== null || $pool['legacy']) {
            throw new RuntimeException(sprintf('The generation pool [%s] (pharaonic.modulator.generation.pool) must be a configured application pool with the app/ layout.', is_scalar($poolName) ? (string) $poolName : ''));
        }

        foreach ($this->registry->sources($id) as $source) {
            throw new RuntimeException($source->package !== null
                ? sprintf('Module [%s] already exists in the package [%s]. Run "php artisan module:publish %s" to get a local copy.', $id, $source->package, $id)
                : sprintf('Module [%s] already exists at [%s].', $id, $source->path));
        }

        $target = $this->target($id, $pool);

        if (file_exists($target)) {
            throw new RuntimeException(sprintf('[%s] already exists. Choose another identity or remove the directory.', $target));
        }

        $namespace = trim($pool['namespace'], '\\').'\\'.Identity::namespace($id);
        $stubs = Stubs::fromConfig($this->config->get('pharaonic.modulator.generation.stubs'));

        Files::locked($this->app->storagePath(), 'make', function () use ($id, $target, $stubs, $namespace, $plain): void {
            $this->create($id, $target, $stubs, $namespace, $plain);
        });

        try {
            $this->manifest->rebuild();
        } catch (RuntimeException $e) {
            // Without a writable cache the next request discovers the module anyway.
        }

        return ['path' => $target, 'namespace' => $namespace];
    }

    /**
     * Where the module goes: under its nearest ancestor module's Modules/
     * folder when that module is in the same pool, otherwise along the
     * identity (a group directory per segment, Modules/ after a module).
     *
     * @param  array{name: string, path: string, namespace: string, priority: int, package: string|null, legacy: bool}  $pool
     *
     * @throws RuntimeException
     */
    private function target(string $id, array $pool): string
    {
        $segments = Identity::segments($id);

        foreach (Identity::ancestors($id) as $ancestor) {
            $parent = $this->registry->find($ancestor);

            if ($parent === null) {
                continue;
            }

            $rest = array_slice($segments, count(Identity::segments($ancestor)));

            if (count($rest) === 1 && in_array($rest[0], Identity::RESERVED_CHILDREN, true)) {
                throw new RuntimeException(sprintf('A child module of [%s] cannot be named [%s]: the parent maps that namespace itself.', $ancestor, $rest[0]));
            }

            if ($parent->pool() === $pool['name']) {
                return $parent->path('Modules/'.implode('/', $rest));
            }

            break;
        }

        $path = rtrim($pool['path'], DIRECTORY_SEPARATOR);
        $prefix = '';

        foreach ($segments as $i => $segment) {
            $prefix = $prefix === '' ? $segment : $prefix.'/'.$segment;
            $path .= DIRECTORY_SEPARATOR.$segment;

            if ($i < count($segments) - 1 && $this->registry->find($prefix) !== null) {
                $path .= DIRECTORY_SEPARATOR.'Modules';
            }
        }

        return $path;
    }

    /**
     * @throws RuntimeException
     */
    private function create(string $id, string $target, Stubs $stubs, string $namespace, bool $plain): void
    {
        $files = new Filesystem;
        $workspace = Files::workspace($this->app->storagePath(), 'tmp').DIRECTORY_SEPARATOR.bin2hex(random_bytes(8));
        $replace = [
            'namespace' => $namespace,
            'name' => Identity::name($id),
            'module' => $id,
            'slug' => Identity::slug($id),
        ];

        try {
            foreach (self::FILES as $stub => $file) {
                if ($plain && $stub !== 'module/provider.stub') {
                    continue;
                }

                $path = str_replace(['{name}', '/'], [Identity::name($id), DIRECTORY_SEPARATOR], $file);
                Files::put($workspace.DIRECTORY_SEPARATOR.$path, $stubs->render($stub, $replace), false);
            }

            foreach ($plain ? [] : self::DIRECTORIES as $directory) {
                Files::put($workspace.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory).DIRECTORY_SEPARATOR.'.gitkeep', '', false);
            }

            if (file_exists($target)) {
                throw new RuntimeException(sprintf('[%s] already exists.', $target));
            }

            $files->ensureDirectoryExists(dirname($target), 0755, true);
            Files::move($workspace, $target);
        } catch (Throwable $e) {
            throw new RuntimeException('The module was not created: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_dir($workspace)) {
                Files::deleteDirectory($workspace);
            }
        }
    }
}
