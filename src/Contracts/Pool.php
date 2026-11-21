<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Contracts;

/**
 * A discovery source: a directory whose subdirectories are modules or groups.
 *
 * Implement it for a pool whose location is computed at runtime, and list the
 * class name under `pharaonic.modulator.pools`. Pools are only read during
 * discovery; they never load module code.
 */
interface Pool
{
    /**
     * A unique name, shown by module:pools and module:source.
     */
    public function name(): string;

    /**
     * Higher wins when several pools hold the same module identity.
     */
    public function priority(): int;

    /**
     * The absolute directory holding the modules.
     */
    public function path(): string;

    /**
     * The logical namespace root, e.g. "Modules".
     */
    public function namespace(): string;

    /**
     * The Composer package that owns the pool, or null for an application pool.
     */
    public function package(): ?string;
}
