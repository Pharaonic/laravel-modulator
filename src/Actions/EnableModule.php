<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\ConfigEditor;
use Pharaonic\Laravel\Modulator\Support\DisabledList;
use Pharaonic\Laravel\Modulator\Support\Identity;
use RuntimeException;

/**
 * Removes a module from the `disabled` list, only when that makes it enabled:
 * a disabled parent or dependency blocks it unless asked to enable those too.
 *
 * @internal
 */
final class EnableModule
{
    public function __construct(private Manifest $manifest, private DisabledList $list) {}

    /**
     * @return array{removed: list<string>, also: list<string>, published: bool} The identities
     *                                                                           removed from the list, the other modules enabled through them, and whether the configuration was published.
     *
     * @throws RuntimeException When the module would stay disabled; nothing is changed.
     */
    public function handle(string $id, bool $withParents = false, bool $withDependencies = false): array
    {
        $disabled = $this->list->read();
        $before = $this->registry($disabled);
        $remove = array_values(array_intersect($this->candidates($before, $id, $withParents, $withDependencies), $disabled));
        $after = $this->registry(array_values(array_diff($disabled, $remove)));
        $module = $after->find($id);

        if ($module === null || ! $module->isEnabled()) {
            $lines = [sprintf('Module [%s] cannot be enabled; nothing was changed.', $id)];

            foreach ($after->explain($id) as $step) {
                $lines[] = sprintf('  %s: %s', $step['module'], $step['reason']);
            }

            $lines[] = 'Enable the blocking module first, or use --with-parents / --with-dependencies.';

            throw new RuntimeException(implode(PHP_EOL, $lines));
        }

        if ($remove === []) {
            return ['removed' => [], 'also' => [], 'published' => false];
        }

        $published = $this->list->edit(static function (ConfigEditor $editor, string $code) use ($remove): string {
            foreach ($remove as $entry) {
                $code = $editor->remove($code, $entry);
            }

            return $code;
        });

        $also = array_values(array_diff(array_keys($after->enabled()), array_keys($before->enabled()), $remove));
        sort($also, SORT_STRING);

        return ['removed' => $remove, 'also' => $also, 'published' => $published];
    }

    /**
     * @param  list<string>  $disabled
     */
    private function registry(array $disabled): ModuleRegistry
    {
        return ModuleRegistry::fromManifest($this->manifest->load(), $disabled);
    }

    /**
     * The module, plus its ancestors and (transitive) dependencies when asked to.
     *
     * @return list<string>
     */
    private function candidates(ModuleRegistry $registry, string $id, bool $withParents, bool $withDependencies): array
    {
        $candidates = [$id];
        $queue = [$id];

        while ($queue !== []) {
            $current = array_shift($queue);
            $next = $withParents ? Identity::ancestors($current) : [];

            if ($withDependencies && ($module = $registry->find($current)) !== null) {
                $next = array_merge($next, $module->dependencies());
            }

            foreach ($next as $candidate) {
                if (! in_array($candidate, $candidates, true)) {
                    $candidates[] = $candidate;
                    $queue[] = $candidate;
                }
            }
        }

        return $candidates;
    }
}
