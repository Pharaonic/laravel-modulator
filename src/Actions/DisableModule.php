<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\ConfigEditor;
use Pharaonic\Laravel\Modulator\Support\DisabledList;
use RuntimeException;

/**
 * Adds a module to the `disabled` list. plan() tells what else stops: its
 * children (they follow it) and the modules that depend on it.
 *
 * @internal
 */
final class DisableModule
{
    public function __construct(private Manifest $manifest, private DisabledList $list) {}

    /**
     * @return array{disabled: bool, children: list<string>, dependents: array<string, string>} Whether it
     *                                                                                          is already listed, the children it disables, and the dependents it disables with their reason.
     *
     * @throws RuntimeException
     */
    public function plan(string $id): array
    {
        $disabled = $this->list->read();

        if (in_array($id, $disabled, true)) {
            return ['disabled' => true, 'children' => [], 'dependents' => []];
        }

        $before = ModuleRegistry::fromManifest($this->manifest->load(), $disabled);
        $after = ModuleRegistry::fromManifest($this->manifest->load(), array_merge($disabled, [$id]));
        $children = [];
        $dependents = [];

        foreach (array_keys($before->enabled()) as $candidate) {
            $now = $after->find($candidate);

            if ($candidate === $id || $now === null || $now->isEnabled()) {
                continue;
            }

            if (str_starts_with($candidate, $id.'/')) {
                $children[] = $candidate;
            } else {
                $dependents[$candidate] = (string) $now->disabledReason();
            }
        }

        return ['disabled' => false, 'children' => $children, 'dependents' => $dependents];
    }

    /**
     * @return bool Whether the configuration was published by this change.
     *
     * @throws RuntimeException
     */
    public function handle(string $id): bool
    {
        return $this->list->edit(static function (ConfigEditor $editor, string $code) use ($id): string {
            return $editor->add($code, $id);
        });
    }
}
