<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Discovery;

use Pharaonic\Laravel\Modulator\Module;

/**
 * One physical occurrence of a module in a pool.
 *
 * @internal
 *
 * @phpstan-type SourceData array{id: string, pool: string, priority: int, path: string, namespace: string, package: string|null, legacy: bool, dependencies: list<string>, exposes: list<string>}
 */
final class Source
{
    /**
     * @param  string  $path  The module root directory (the one holding app/).
     * @param  string  $namespace  The module namespace, without a trailing backslash.
     * @param  list<string>  $dependencies  Module identities declared in module.php.
     * @param  list<string>  $exposes  Public namespaces (relative to the module) declared in module.php.
     */
    public function __construct(
        public string $id,
        public string $pool,
        public int $priority,
        public string $path,
        public string $namespace,
        public ?string $package = null,
        public bool $legacy = false,
        public array $dependencies = [],
        public array $exposes = []
    ) {}

    /**
     * The directory holding the module classes: app/ (legacy modules: the module root).
     */
    public function classPath(): string
    {
        return $this->legacy ? $this->path : $this->path.DIRECTORY_SEPARATOR.'app';
    }

    /**
     * PSR-4 prefixes owned by this source, most specific first.
     *
     * Legacy modules have none: the application's own "App\" mapping loads them.
     *
     * @return array<string, string>
     */
    public function prefixes(): array
    {
        if ($this->legacy) {
            return [];
        }

        $ds = DIRECTORY_SEPARATOR;

        return [
            $this->namespace.'\\Database\\Factories\\' => $this->path.$ds.'database'.$ds.'factories',
            $this->namespace.'\\Database\\Seeders\\' => $this->path.$ds.'database'.$ds.'seeders',
            $this->namespace.'\\Tests\\' => $this->path.$ds.'tests',
            $this->namespace.'\\' => $this->classPath(),
        ];
    }

    /**
     * @return SourceData
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pool' => $this->pool,
            'priority' => $this->priority,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'package' => $this->package,
            'legacy' => $this->legacy,
            'dependencies' => $this->dependencies,
            'exposes' => $this->exposes,
        ];
    }

    /**
     * @param  SourceData  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['pool'],
            $data['priority'],
            $data['path'],
            $data['namespace'],
            $data['package'],
            $data['legacy'],
            $data['dependencies'],
            $data['exposes']
        );
    }
}
