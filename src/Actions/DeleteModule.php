<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Files;
use RuntimeException;

/**
 * Deletes an application module directory. plan() refuses package modules,
 * anything outside the module's pool, symlinks, modules others depend on, and
 * modules holding child modules (unless they go too).
 *
 * @internal
 */
final class DeleteModule
{
    public function __construct(
        private Application $app,
        private Manifest $manifest,
        private ModuleRegistry $registry
    ) {
    }

    /**
     * @return array{id: string, path: string, children: list<string>, replaced: bool, disabled: bool} The
     *                                                                                                   module path, the child modules deleted with it, whether another source takes over, and whether it stays listed as disabled.
     *
     * @throws RuntimeException When the module must not be deleted.
     */
    public function plan(string $id, bool $withChildren = false): array
    {
        $module = $this->registry->find($id);

        if ($module === null) {
            throw new RuntimeException(sprintf('Module [%s] does not exist. Run module:list to see the modules.', $id));
        }

        if ($module->package() !== null) {
            throw new RuntimeException(sprintf('Module [%s] belongs to the package [%s]. Remove the package with Composer instead; vendor code is never deleted.', $id, $module->package()));
        }

        $pool = $this->manifest->pool($module->pool());
        $path = $module->path();

        if ($pool === null || $pool['package'] !== null || is_link($path) || ! Files::isInside($path, $pool['path']) || realpath($path) === realpath($pool['path'])) {
            throw new RuntimeException(sprintf('[%s] is not a directory inside the pool [%s]; it is not deleted.', $path, $module->pool()));
        }

        $vendor = $this->app->basePath('vendor');

        if (is_dir($vendor) && Files::isInside($path, $vendor)) {
            throw new RuntimeException(sprintf('[%s] is inside vendor/; it is not deleted.', $path));
        }

        $children = [];

        foreach ($this->registry->all() as $candidate) {
            if (str_starts_with($candidate->id(), $id.'/') && $candidate->pool() === $module->pool() && Files::isInside($candidate->path(), $path)) {
                $children[] = $candidate->id();
            }
        }

        $removed = array_merge([$id], $children);
        $dependents = [];

        foreach ($removed as $removedId) {
            foreach ($this->registry->dependents($removedId) as $dependent) {
                if (! in_array($dependent, $removed, true)) {
                    $dependents[$dependent] = true;
                }
            }
        }

        if ($dependents !== []) {
            throw new RuntimeException(sprintf('Nothing was deleted: [%s] depend on it. Remove those dependencies first.', implode('], [', array_keys($dependents))));
        }

        if ($children !== [] && ! $withChildren) {
            throw new RuntimeException(sprintf('Nothing was deleted: the module holds the child modules [%s]. Move them out, or run again with --with-children.', implode('], [', $children)));
        }

        return [
            'id' => $id,
            'path' => $path,
            'children' => $children,
            'replaced' => count($this->registry->sources($id)) > 1,
            'disabled' => $this->registry->isDisabledInConfig($id),
        ];
    }

    /**
     * Move the module out of its pool, then delete it.
     *
     * @param  array{id: string, path: string, children: list<string>, replaced: bool, disabled: bool}  $plan
     *
     * @throws RuntimeException
     */
    public function handle(array $plan): void
    {
        $trash = Files::workspace($this->app->storagePath(), 'tmp').DIRECTORY_SEPARATOR.'deleted-'.bin2hex(random_bytes(8));
        Files::move($plan['path'], $trash);
        (new Filesystem)->deleteDirectory($trash);

        try {
            $this->manifest->rebuild();
        } catch (RuntimeException $e) {
            // Without a writable cache the next request discovers again anyway.
        }
    }
}
