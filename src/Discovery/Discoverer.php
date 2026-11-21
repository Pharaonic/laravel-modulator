<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Discovery;

use Pharaonic\Laravel\Modulator\Contracts\Pool;
use Pharaonic\Laravel\Modulator\DirectoryPool;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use Pharaonic\Laravel\Modulator\Support\Identity;
use Throwable;

/**
 * Walks pool directories and returns every module source they hold.
 *
 * A directory with app/ is a module; its children live in its Modules/ folder.
 * A directory without app/ is a namespace group; its children are its own
 * subdirectories. A folder named "Modules" never adds an identity segment, so
 * "Commerce/Checkout" may live at Commerce/Checkout or Commerce/Modules/Checkout.
 * Legacy pools use the 1.x rule instead: a directory with Providers/ is a module.
 *
 * @internal
 */
final class Discoverer
{
    /**
     * @var list<Diagnostic>
     */
    private array $diagnostics = [];

    /**
     * @var array<string, true>
     */
    private array $visited = [];

    /**
     * Directories and files whose change means the discovery result may change.
     *
     * @var array<string, true>
     */
    private array $watch = [];

    /**
     * @param  list<Pool>  $pools
     * @return array{sources: list<Source>, diagnostics: list<Diagnostic>, watch: list<string>}
     */
    public function discover(array $pools): array
    {
        $this->diagnostics = [];
        $this->watch = [];
        $sources = [];

        foreach ($pools as $pool) {
            $this->visited = [];
            $root = $pool->path();

            if (! is_dir($root)) {
                $this->watch[$root] = true;

                continue;
            }

            $legacy = $pool instanceof DirectoryPool && $pool->isLegacy();
            $found = [];

            foreach ($this->walk($pool, $root, '', $legacy) as $source) {
                if (isset($found[$source->id])) {
                    $this->diagnostics[] = Diagnostic::error(sprintf(
                        'Module [%s] exists twice in pool [%s]: %s and %s. Keep one of them.',
                        $source->id,
                        $pool->name(),
                        $found[$source->id]->path,
                        $source->path
                    ), $source->id);

                    continue;
                }

                $found[$source->id] = $source;
            }

            foreach ($found as $source) {
                $sources[] = $source;
            }
        }

        $watch = array_keys($this->watch);
        sort($watch, SORT_STRING);

        return ['sources' => $sources, 'diagnostics' => $this->diagnostics, 'watch' => $watch];
    }

    /**
     * @return list<Source>
     */
    private function walk(Pool $pool, string $directory, string $prefix, bool $legacy): array
    {
        $real = realpath($directory);

        if ($real === false || isset($this->visited[$real])) {
            return [];
        }

        $this->visited[$real] = true;
        $this->watch[$real] = true;
        $sources = [];

        foreach ($this->directories($directory) as $name) {
            $path = $directory.DIRECTORY_SEPARATOR.$name;

            if ($name === 'Modules') {
                array_push($sources, ...$this->walk($pool, $path, $prefix, $legacy));

                continue;
            }

            $isModule = $legacy ? is_dir($path.DIRECTORY_SEPARATOR.'Providers') : is_dir($path.DIRECTORY_SEPARATOR.'app');

            if (! Identity::isValidSegment($name)) {
                if ($isModule) {
                    $this->diagnostics[] = Diagnostic::error(sprintf(
                        'Directory [%s] in pool [%s] looks like a module but [%s] is not a valid identity segment. Rename it to StudlyCase (letters and digits, starting with an uppercase letter).',
                        $path,
                        $pool->name(),
                        $name
                    ));
                }

                continue;
            }

            $id = $prefix === '' ? $name : $prefix.'/'.$name;

            if (count(Identity::segments($id)) > Identity::MAX_DEPTH) {
                $this->diagnostics[] = Diagnostic::error(sprintf('Module hierarchy at [%s] is deeper than %d levels.', $path, Identity::MAX_DEPTH));

                continue;
            }

            if ($isModule) {
                $sources[] = $this->source($pool, $id, $path, $legacy);

                if (! $legacy) {
                    array_push($sources, ...$this->walk($pool, $path.DIRECTORY_SEPARATOR.'Modules', $id, $legacy));
                }

                continue;
            }

            array_push($sources, ...$this->walk($pool, $path, $id, $legacy));
        }

        return $sources;
    }

    private function source(Pool $pool, string $id, string $path, bool $legacy): Source
    {
        $real = realpath($path);
        $root = $real === false ? $path : $real;
        $this->watch[$root] = true;

        if (is_file($root.DIRECTORY_SEPARATOR.'module.php')) {
            $this->watch[$root.DIRECTORY_SEPARATOR.'module.php'] = true;
        }

        $definition = $this->definition($id, $root.DIRECTORY_SEPARATOR.'module.php');

        return new Source(
            $id,
            $pool->name(),
            $pool->priority(),
            $root,
            trim($pool->namespace(), '\\').'\\'.Identity::namespace($id),
            $pool->package(),
            $legacy,
            $definition['dependencies'],
            $definition['exposes']
        );
    }

    /**
     * Read the optional module.php definition (an array, no classes involved).
     *
     * @return array{dependencies: list<string>, exposes: list<string>}
     */
    private function definition(string $id, string $file): array
    {
        $definition = ['dependencies' => [], 'exposes' => []];

        if (! is_file($file)) {
            return $definition;
        }

        try {
            $data = (static function (string $file) {
                return require $file;
            })($file);
        } catch (Throwable $e) {
            $this->diagnostics[] = Diagnostic::error(sprintf('Cannot read [%s]: %s', $file, $e->getMessage()), $id);

            return $definition;
        }

        if (! is_array($data)) {
            $this->diagnostics[] = Diagnostic::error(sprintf('[%s] must return an array.', $file), $id);

            return $definition;
        }

        foreach (array_diff(array_keys($data), ['dependencies', 'exposes']) as $key) {
            $this->diagnostics[] = Diagnostic::warning(sprintf('Unknown key [%s] in [%s] is ignored.', (string) $key, $file), $id);
        }

        foreach (['dependencies', 'exposes'] as $key) {
            $values = $data[$key] ?? [];

            if (! is_array($values)) {
                $this->diagnostics[] = Diagnostic::error(sprintf('[%s] in [%s] must be a list of strings.', $key, $file), $id);

                continue;
            }

            foreach ($values as $value) {
                $valid = is_string($value) && ($key === 'dependencies'
                    ? Identity::isValid($value) && $value !== $id
                    : preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $value) === 1);

                if (! $valid) {
                    $this->diagnostics[] = Diagnostic::error(sprintf(
                        'Invalid entry %s in [%s] of [%s]: %s',
                        is_scalar($value) ? '['.$value.']' : 'of type '.gettype($value),
                        $key,
                        $file,
                        $key === 'dependencies' ? 'expected another module identity such as "Catalog" or "Commerce/Checkout".' : 'expected a namespace relative to the module, such as "Contracts".'
                    ), $id);

                    continue;
                }

                /** @var string $value */
                $definition[$key][] = $value;
            }

            $definition[$key] = array_values(array_unique($definition[$key]));
        }

        return $definition;
    }

    /**
     * Subdirectory names, sorted byte-wise so discovery never depends on the filesystem order.
     *
     * @return list<string>
     */
    private function directories(string $directory): array
    {
        $entries = @scandir($directory);

        if ($entries === false) {
            return [];
        }

        $names = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }

            if (is_dir($directory.DIRECTORY_SEPARATOR.$entry)) {
                $names[] = $entry;
            }
        }

        sort($names, SORT_STRING);

        return $names;
    }
}
