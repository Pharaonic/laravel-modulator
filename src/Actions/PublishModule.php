<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use FilesystemIterator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Files;
use Pharaonic\Laravel\Modulator\Support\Identity;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Copies a package-provided module into the local pool, where it shadows the
 * package copy under the same identity.
 *
 * The package is only read. The copy is assembled in a private workspace
 * under storage/framework/modulator, then moved into place; a replaced local
 * copy is kept as a backup and restored if anything fails.
 *
 * @internal
 */
final class PublishModule
{
    private Filesystem $files;

    private string $storage;

    public function __construct(Application $app)
    {
        $this->files = new Filesystem;
        $this->storage = $app->storagePath();
    }

    /**
     * Compute what publishing would do, without writing anything.
     *
     * @param  array{name: string, path: string, namespace: string, priority: int, package: string|null, legacy: bool}  $target  The local pool.
     * @param  list<array{name: string, path: string, namespace: string, priority: int, package: string|null, legacy: bool}>  $pools
     * @return array{source: Source, target: string, added: list<string>, changed: list<string>, removed: list<string>, exists: bool}
     *
     * @throws RuntimeException
     */
    public function plan(ModuleRegistry $registry, string $id, array $target, array $pools, bool $withChildren): array
    {
        $source = null;

        foreach ($registry->sources($id) as $candidate) {
            if ($candidate->package !== null) {
                $source = $candidate;

                break;
            }
        }

        if ($source === null) {
            throw new RuntimeException(sprintf('Module [%s] is not provided by a package, so there is nothing to publish.', $id));
        }

        if ($target['package'] !== null || $target['legacy']) {
            throw new RuntimeException(sprintf('Pool [%s] cannot receive published modules: it must be an application pool with the app/ layout.', $target['name']));
        }

        if ($source->namespace !== trim($target['namespace'], '\\').'\\'.Identity::namespace($id)) {
            throw new RuntimeException(sprintf(
                'Module [%s] has the namespace [%s] in its package but would get [%s] in pool [%s]. Publishing would change its classes; use a local pool with the namespace root of the package pool.',
                $id,
                $source->namespace,
                trim($target['namespace'], '\\').'\\'.Identity::namespace($id),
                $target['name']
            ));
        }

        $poolRoot = null;

        foreach ($pools as $pool) {
            if ($pool['name'] === $source->pool) {
                $poolRoot = $pool['path'];
            }
        }

        if ($poolRoot === null || ! str_starts_with($source->path, $poolRoot.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(sprintf('Cannot locate module [%s] inside its pool [%s].', $id, $source->pool));
        }

        $relative = substr($source->path, strlen($poolRoot) + 1);
        $destination = rtrim($target['path'], '/\\').DIRECTORY_SEPARATOR.$relative;

        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException(sprintf('Refusing to publish module [%s] to an unsafe path [%s].', $id, $destination));
            }
        }

        $incoming = $this->hashes($source->path, $withChildren);
        $existing = is_dir($destination) ? $this->hashes($destination, $withChildren) : [];

        $added = array_keys(array_diff_key($incoming, $existing));
        $removed = array_keys(array_diff_key($existing, $incoming));
        $changed = [];

        foreach (array_intersect_key($incoming, $existing) as $file => $hash) {
            if ($existing[$file] !== $hash) {
                $changed[] = $file;
            }
        }

        return [
            'source' => $source,
            'target' => $destination,
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'exists' => is_dir($destination),
        ];
    }

    /**
     * Apply a plan.
     *
     * @param  array{source: Source, target: string, added: list<string>, changed: list<string>, removed: list<string>, exists: bool}  $plan
     * @return string|null The backup directory of the replaced local copy.
     *
     * @throws RuntimeException
     */
    public function publish(array $plan, bool $withChildren): ?string
    {
        return Files::locked($this->storage, 'publish', function () use ($plan, $withChildren): ?string {
            return $this->copy($plan, $withChildren);
        });
    }

    /**
     * @param  array{source: Source, target: string, added: list<string>, changed: list<string>, removed: list<string>, exists: bool}  $plan
     */
    private function copy(array $plan, bool $withChildren): ?string
    {
        $workspace = Files::workspace($this->storage);
        $temporary = Files::workspace($this->storage, 'tmp').DIRECTORY_SEPARATOR.bin2hex(random_bytes(8));
        $backup = null;
        $target = $plan['target'];

        try {
            foreach (array_keys($this->hashes($plan['source']->path, $withChildren)) as $file) {
                $to = $temporary.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
                $this->files->ensureDirectoryExists(dirname($to), 0755, true);

                if (! copy($plan['source']->path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file), $to)) {
                    throw new RuntimeException(sprintf('Cannot copy [%s].', $file));
                }
            }

            if (! is_dir($temporary.DIRECTORY_SEPARATOR.'app')) {
                throw new RuntimeException('The published copy has no app/ directory.');
            }

            if (is_dir($target)) {
                $backup = $workspace.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.Identity::slug($plan['source']->id).'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
                $this->files->ensureDirectoryExists(dirname($backup), 0755, true);
                Files::move($target, $backup);

                // Child modules stay local unless they are published too.
                if (! $withChildren && is_dir($backup.DIRECTORY_SEPARATOR.'Modules')) {
                    $this->files->copyDirectory($backup.DIRECTORY_SEPARATOR.'Modules', $temporary.DIRECTORY_SEPARATOR.'Modules');
                }
            }

            $this->files->ensureDirectoryExists(dirname($target), 0755, true);
            Files::move($temporary, $target);
        } catch (Throwable $e) {
            if ($backup !== null && is_dir($backup)) {
                if (is_dir($target)) {
                    $this->files->deleteDirectory($target);
                }

                Files::move($backup, $target);
                $backup = null;
            }

            throw new RuntimeException('Publishing failed and nothing was changed: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_dir($temporary)) {
                $this->files->deleteDirectory($temporary);
            }
        }

        return $backup;
    }

    /**
     * Relative file path (with "/") => SHA-1, sorted.
     *
     * @return array<string, string>
     */
    private function hashes(string $root, bool $withChildren): array
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

            if (! $withChildren && str_starts_with($relative, 'Modules/')) {
                continue;
            }

            $hashes[$relative] = (string) sha1_file($file->getPathname());
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }
}
