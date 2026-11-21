<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator;

use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use Pharaonic\Laravel\Modulator\Support\Identity;

/**
 * Every discovered logical module, its selected source and its effective state.
 *
 * Selection: the highest-priority source of an identity wins and the others are
 * shadowed. Equal priorities are a collision: the identity is not resolved.
 * State is computed after selection, so a disabled winner never falls back to
 * a shadowed source.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, Module>
     */
    private array $modules = [];

    /**
     * @var array<string, list<Source>>
     */
    private array $sources = [];

    /**
     * @var list<string>
     */
    private array $order = [];

    /**
     * @var list<Diagnostic>
     */
    private array $diagnostics;

    /**
     * @var array<string, Source>
     */
    private array $selected = [];

    /**
     * @var array<string, string|null>
     */
    private array $parents = [];

    /**
     * @var array<string, true>
     */
    private array $configuredDisabled = [];

    /**
     * @var array<string, string>
     */
    private array $cycles = [];

    /**
     * @var array<string, array{0: bool, 1: string|null}>
     */
    private array $states = [];

    /**
     * The module whose state disables another one (parent or dependency).
     *
     * @var array<string, string>
     */
    private array $blockers = [];

    /**
     * @internal Built by the service provider from the manifest.
     *
     * @param  list<Source>  $sources
     * @param  list<mixed>  $disabled  The `pharaonic.modulator.disabled` list.
     * @param  list<Diagnostic>  $diagnostics  Discovery findings.
     */
    public function __construct(array $sources, array $disabled = [], array $diagnostics = [])
    {
        $this->diagnostics = $diagnostics;

        $this->group($sources);
        $this->select();
        $this->resolveParents();
        $this->readDisabled($disabled);
        $this->detectCycles();
        $this->build();
    }

    /**
     * @internal
     *
     * @param  array{sources: list<array{id: string, pool: string, priority: int, path: string, namespace: string, package: string|null, legacy: bool, dependencies: list<string>, exposes: list<string>}>, diagnostics: list<array{level: string, message: string, module: string|null}>}  $manifest
     * @param  mixed  $disabled  The `pharaonic.modulator.disabled` value.
     */
    public static function fromManifest(array $manifest, $disabled): self
    {
        return new self(
            array_map([Source::class, 'fromArray'], $manifest['sources']),
            is_array($disabled) ? array_values($disabled) : [$disabled],
            array_map([Diagnostic::class, 'fromArray'], $manifest['diagnostics'])
        );
    }

    /**
     * Every resolved module, enabled or not, keyed by identity in identity order.
     *
     * @return array<string, Module>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * The effectively enabled modules, keyed by identity, in load order
     * (parents and dependencies before the modules that need them).
     *
     * @return array<string, Module>
     */
    public function enabled(): array
    {
        $enabled = [];

        foreach ($this->order as $id) {
            if ($this->modules[$id]->isEnabled()) {
                $enabled[$id] = $this->modules[$id];
            }
        }

        return $enabled;
    }

    /**
     * The module with this identity, or null when no source resolves it.
     */
    public function find(string $id): ?Module
    {
        return $this->modules[$id] ?? null;
    }

    /**
     * Every source of an identity, winner first, then the shadowed ones.
     *
     * @internal
     *
     * @return list<Source>
     */
    public function sources(string $id): array
    {
        return $this->sources[$id] ?? [];
    }

    /**
     * Whether the identity is listed under `disabled`.
     *
     * @internal
     */
    public function isDisabledInConfig(string $id): bool
    {
        return isset($this->configuredDisabled[$id]);
    }

    /**
     * Why a module is disabled, from the module itself down to the cause:
     * one reason per module of the chain (parent or dependency).
     *
     * @internal
     *
     * @return list<array{module: string, reason: string}>
     */
    public function explain(string $id): array
    {
        $chain = [];

        while (isset($this->modules[$id]) && ! $this->modules[$id]->isEnabled() && ! isset($chain[$id])) {
            $chain[$id] = ['module' => $id, 'reason' => (string) $this->modules[$id]->disabledReason()];

            if (! isset($this->blockers[$id])) {
                break;
            }

            $id = $this->blockers[$id];
        }

        return array_values($chain);
    }

    /**
     * Modules that declare this one as a dependency, directly or through
     * other dependencies (children are not included: they follow their parent).
     *
     * @internal
     *
     * @return list<string>
     */
    public function dependents(string $id): array
    {
        $found = [];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->selected as $candidate => $source) {
                if (! isset($found[$candidate]) && $candidate !== $id && in_array($current, $source->dependencies, true)) {
                    $found[$candidate] = true;
                    $queue[] = $candidate;
                }
            }
        }

        $found = array_keys($found);
        sort($found, SORT_STRING);

        return $found;
    }

    /**
     * Identities listed under `disabled`, in configuration order.
     *
     * @internal
     *
     * @return list<string>
     */
    public function configuredDisabled(): array
    {
        return array_keys($this->configuredDisabled);
    }

    /**
     * @internal
     *
     * @return list<Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @param  list<Source>  $sources
     */
    private function group(array $sources): void
    {
        foreach ($sources as $source) {
            $this->sources[$source->id][] = $source;
        }

        ksort($this->sources, SORT_STRING);

        foreach ($this->sources as $id => $list) {
            usort($list, static function (Source $a, Source $b): int {
                return [$b->priority, $a->pool] <=> [$a->priority, $b->pool];
            });

            $this->sources[$id] = $list;
        }
    }

    private function select(): void
    {
        $lower = [];

        foreach (array_keys($this->sources) as $id) {
            $lower[strtolower($id)][] = $id;
        }

        foreach ($lower as $ids) {
            if (count($ids) > 1) {
                $this->diagnostics[] = Diagnostic::error(sprintf(
                    'Module identities [%s] differ only by case. They cannot be resolved on case-insensitive filesystems; rename one of them.',
                    implode('], [', $ids)
                ));

                foreach ($ids as $id) {
                    unset($this->sources[$id]);
                }
            }
        }

        foreach ($this->sources as $id => $list) {
            if (isset($list[1]) && $list[1]->priority === $list[0]->priority) {
                $tied = array_filter($list, static function (Source $source) use ($list): bool {
                    return $source->priority === $list[0]->priority;
                });

                $this->diagnostics[] = Diagnostic::error(sprintf(
                    'Module [%s] is provided with the same priority (%d) by pools [%s]. Give one pool a higher priority or remove a copy.',
                    $id,
                    $list[0]->priority,
                    implode('], [', array_map(static function (Source $source): string {
                        return $source->pool;
                    }, $tied))
                ), $id);

                continue;
            }

            $this->selected[$id] = $list[0];
        }
    }

    private function resolveParents(): void
    {
        foreach ($this->selected as $id => $source) {
            $parent = null;

            foreach (Identity::ancestors($id) as $ancestor) {
                if (isset($this->selected[$ancestor])) {
                    $parent = $ancestor;

                    break;
                }
            }

            $this->parents[$id] = $parent;

            if ($parent === null) {
                continue;
            }

            $name = Identity::name($id);
            $parentSource = $this->selected[$parent];

            if (in_array($name, Identity::RESERVED_CHILDREN, true) && count(Identity::segments($id)) === count(Identity::segments($parent)) + 1) {
                $this->diagnostics[] = Diagnostic::error(sprintf(
                    'Module [%s] cannot be named [%s]: its parent [%s] maps "%s\\%s\\" itself. Rename the child module.',
                    $id,
                    $name,
                    $parent,
                    $parentSource->namespace,
                    $name
                ), $id);
            }

            $overlap = $parentSource->classPath().DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, array_slice(Identity::segments($id), count(Identity::segments($parent))));

            if (! $parentSource->legacy && is_dir($overlap)) {
                $this->diagnostics[] = Diagnostic::error(sprintf(
                    'Module [%s] shares its namespace [%s\\] with the folder [%s] of its parent [%s]. Classes there are not autoloaded; move them or rename the child module.',
                    $id,
                    $source->namespace,
                    $overlap,
                    $parent
                ), $id);
            }
        }
    }

    /**
     * @param  list<mixed>  $disabled
     */
    private function readDisabled(array $disabled): void
    {
        foreach ($disabled as $id) {
            if (! is_string($id) || ! Identity::isValid($id)) {
                $this->diagnostics[] = Diagnostic::error(sprintf(
                    'Invalid entry %s in [pharaonic.modulator.disabled]: expected a module identity such as "Shop" or "Commerce/Checkout".',
                    is_scalar($id) ? '['.$id.']' : 'of type '.gettype($id)
                ));

                continue;
            }

            $this->configuredDisabled[$id] = true;

            if (! isset($this->sources[$id])) {
                $this->diagnostics[] = Diagnostic::warning(sprintf('[pharaonic.modulator.disabled] lists [%s], which is not a discovered module.', $id), $id);
            }
        }
    }

    /**
     * Requirement edges: a module needs its parent and its dependencies.
     *
     * @return list<string>
     */
    private function requirements(string $id): array
    {
        $edges = $this->selected[$id]->dependencies;
        $parent = $this->parents[$id] ?? null;

        if ($parent !== null) {
            array_unshift($edges, $parent);
        }

        return array_values(array_unique($edges));
    }

    /**
     * Mark every module that belongs to a requirement cycle (Tarjan's SCC).
     */
    private function detectCycles(): void
    {
        $index = 0;
        $stack = [];
        $onStack = [];
        $indexes = [];
        $lowlinks = [];

        $connect = function (string $id) use (&$connect, &$index, &$stack, &$onStack, &$indexes, &$lowlinks): void {
            $indexes[$id] = $lowlinks[$id] = $index++;
            $stack[] = $id;
            $onStack[$id] = true;

            foreach ($this->requirements($id) as $next) {
                if (! isset($this->selected[$next])) {
                    continue;
                }

                if (! isset($indexes[$next])) {
                    $connect($next);
                    $lowlinks[$id] = min($lowlinks[$id], $lowlinks[$next]);
                } elseif (isset($onStack[$next])) {
                    $lowlinks[$id] = min($lowlinks[$id], $indexes[$next]);
                }
            }

            if ($lowlinks[$id] !== $indexes[$id]) {
                return;
            }

            $component = [];

            do {
                $member = array_pop($stack);
                unset($onStack[$member]);
                $component[] = $member;
            } while ($member !== $id);

            if (count($component) === 1 && ! in_array($id, $this->requirements($id), true)) {
                return;
            }

            sort($component, SORT_STRING);
            $description = 'it is part of a requirement cycle between ['.implode('], [', $component).']';

            foreach ($component as $member) {
                $this->cycles[$member] = $description;
            }

            $this->diagnostics[] = Diagnostic::error(sprintf(
                'Modules [%s] require each other (through module.php dependencies or a parent). None of them is enabled.',
                implode('], [', $component)
            ));
        };

        foreach (array_keys($this->selected) as $id) {
            if (! isset($indexes[$id])) {
                $connect($id);
            }
        }
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function state(string $id): array
    {
        if (isset($this->states[$id])) {
            return $this->states[$id];
        }

        if (isset($this->cycles[$id])) {
            return $this->states[$id] = [false, 'Disabled because '.$this->cycles[$id].'.'];
        }

        if (isset($this->configuredDisabled[$id])) {
            return $this->states[$id] = [false, 'Disabled in configuration (pharaonic.modulator.disabled).'];
        }

        $parent = $this->parents[$id] ?? null;

        if ($parent !== null && ! $this->state($parent)[0]) {
            $this->blockers[$id] = $parent;

            return $this->states[$id] = [false, sprintf('Disabled because its parent [%s] is disabled.', $parent)];
        }

        foreach ($this->selected[$id]->dependencies as $dependency) {
            if (! isset($this->selected[$dependency])) {
                return $this->states[$id] = [false, sprintf('Disabled because its dependency [%s] is missing.', $dependency)];
            }

            if (! $this->state($dependency)[0]) {
                $this->blockers[$id] = $dependency;

                return $this->states[$id] = [false, sprintf('Disabled because its dependency [%s] is disabled.', $dependency)];
            }
        }

        return $this->states[$id] = [true, null];
    }

    private function build(): void
    {
        $children = [];

        foreach ($this->parents as $id => $parent) {
            if ($parent !== null) {
                $children[$parent][] = $id;
            }
        }

        foreach ($this->selected as $id => $source) {
            [$enabled, $reason] = $this->state($id);

            if (! $enabled && $reason !== null && str_contains($reason, 'its dependency')) {
                $message = sprintf('Module [%s] is not enabled. %s', $id, $reason);
                $this->diagnostics[] = str_contains($reason, 'is missing') ? Diagnostic::error($message, $id) : Diagnostic::warning($message, $id);
            }

            $this->modules[$id] = new Module($source, $enabled, $reason, $this->parents[$id], $children[$id] ?? []);
        }

        $visited = [];
        $visit = function (string $id) use (&$visit, &$visited): void {
            if (isset($visited[$id])) {
                return;
            }

            $visited[$id] = true;

            foreach ($this->requirements($id) as $next) {
                if (isset($this->selected[$next])) {
                    $visit($next);
                }
            }

            $this->order[] = $id;
        };

        foreach (array_keys($this->selected) as $id) {
            $visit($id);
        }
    }
}
