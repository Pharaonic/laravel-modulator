<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator;

use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\Support\Identity;

/**
 * A logical module: one identity, resolved to the source that won selection.
 */
final class Module
{
    /**
     * @internal Created by the ModuleRegistry.
     *
     * @param  list<string>  $children
     */
    public function __construct(
        private Source $source,
        private bool $enabled,
        private ?string $disabledReason,
        private ?string $parent,
        private array $children
    ) {
    }

    /**
     * The identity, e.g. "Shop" or "Commerce/Checkout".
     */
    public function id(): string
    {
        return $this->source->id;
    }

    /**
     * The last identity segment, e.g. "Checkout".
     */
    public function name(): string
    {
        return Identity::name($this->source->id);
    }

    /**
     * The namespace root, e.g. "Modules\Commerce\Checkout".
     */
    public function namespace(): string
    {
        return $this->source->namespace;
    }

    /**
     * The view, translation and configuration namespace, e.g. "commerce-checkout".
     */
    public function slug(): string
    {
        return Identity::slug($this->source->id);
    }

    /**
     * The module root directory, or a path inside it.
     */
    public function path(?string $path = null): string
    {
        if ($path === null || $path === '') {
            return $this->source->path;
        }

        return $this->source->path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
    }

    /**
     * The name of the pool the module was resolved from.
     */
    public function pool(): string
    {
        return $this->source->pool;
    }

    /**
     * The Composer package that provides the module, or null for an application module.
     */
    public function package(): ?string
    {
        return $this->source->package;
    }

    /**
     * The identity of the nearest ancestor that is a module, or null.
     */
    public function parent(): ?string
    {
        return $this->parent;
    }

    /**
     * Identities of the modules whose parent is this module.
     *
     * @return list<string>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * Identities declared in module.php under "dependencies".
     *
     * @return list<string>
     */
    public function dependencies(): array
    {
        return $this->source->dependencies;
    }

    /**
     * Whether the module is effectively enabled: not disabled in configuration,
     * its parent is enabled, and every dependency exists and is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Why the module is disabled, or null when it is enabled.
     */
    public function disabledReason(): ?string
    {
        return $this->disabledReason;
    }

    /**
     * @internal
     */
    public function source(): Source
    {
        return $this->source;
    }
}
