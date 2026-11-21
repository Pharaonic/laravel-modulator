<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Concerns;

use InvalidArgumentException;
use Pharaonic\Laravel\Modulator\Module;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Context;
use Pharaonic\Laravel\Modulator\Support\Identity;
use Pharaonic\Laravel\Modulator\Support\Resolve;

/**
 * Module argument resolution for commands.
 *
 * Order: the explicit argument, then the module selected with module:use
 * (interactive consoles outside CI and production only), then an interactive
 * choice; otherwise an error asking for the identity.
 *
 * @internal
 *
 * @mixin \Illuminate\Console\Command
 */
trait ResolvesModules
{
    protected function registry(): ModuleRegistry
    {
        return Resolve::make($this->laravel, ModuleRegistry::class);
    }

    protected function moduleContext(): Context
    {
        return new Context($this->laravel->storagePath());
    }

    /**
     * The module the command targets, or null after printing why there is none.
     *
     * @param  mixed  $argument  The raw module argument.
     */
    protected function resolveModule($argument): ?Module
    {
        $registry = $this->registry();

        if (is_string($argument) && $argument !== '') {
            return $this->findModule($registry, $argument);
        }

        $trusted = Context::isTrusted($this->input->isInteractive(), $this->laravel->environment() === 'production');

        if ($trusted && ($current = $this->moduleContext()->get()) !== null) {
            $module = $this->findModule($registry, $current, ' Select another one with module:use, or clear it with module:use --clear.');

            if ($module !== null) {
                $this->line(sprintf('<comment>Module [%s] (selected with module:use).</comment>', $module->id()));
            }

            return $module;
        }

        $ids = array_keys($registry->all());

        if ($trusted && $ids !== []) {
            $choice = $this->choice('Which module?', $ids);

            return is_string($choice) ? $registry->find($choice) : null;
        }

        $this->error($ids === []
            ? 'No modules found. Create one with module:make.'
            : 'Give the module identity, e.g. "Shop" or "Commerce/Checkout". A module selected with module:use is only used on interactive consoles, outside CI and production.');

        return null;
    }

    private function findModule(ModuleRegistry $registry, string $id, string $hint = ''): ?Module
    {
        try {
            Identity::assert($id);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage().$hint);

            return null;
        }

        $module = $registry->find($id);

        if ($module === null) {
            $this->error(sprintf('Module [%s] does not exist.%s', $id, $hint === '' ? ' Run module:list to see the modules.' : $hint));
        }

        return $module;
    }
}
