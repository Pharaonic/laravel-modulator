<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator;

use Pharaonic\Laravel\Modulator\Contracts\Pool;

/**
 * The default pool: a fixed directory, as configured or declared by a package.
 */
final class DirectoryPool implements Pool
{
    /**
     * @param  bool  $legacy  Whether the pool holds 1.x modules (classes at the module root, a Providers/ folder per module).
     */
    public function __construct(
        private string $name,
        private string $path,
        private string $namespace = 'Modules',
        private int $priority = 0,
        private ?string $package = null,
        private bool $legacy = false
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function namespace(): string
    {
        return trim($this->namespace, '\\');
    }

    public function package(): ?string
    {
        return $this->package;
    }

    public function isLegacy(): bool
    {
        return $this->legacy;
    }
}
