<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as FoundationApplication;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Modulator\Autoload\Autoloader;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;
use Pharaonic\Laravel\Modulator\Support\ModuleLock;
use RuntimeException;

/**
 * Everything module:doctor checks: a fresh discovery (sources, states,
 * dependencies, autoload conflicts), the discovery cache, the module
 * providers and the configuration cache.
 *
 * @internal
 */
final class DiagnoseModules
{
    public function __construct(
        private Application $app,
        private Manifest $manifest,
        private Autoloader $autoloader,
        private Repository $config
    ) {}

    /**
     * @return array{registry: ModuleRegistry, diagnostics: list<Diagnostic>}
     */
    public function handle(): array
    {
        $fresh = $this->manifest->fresh();
        $registry = ModuleRegistry::fromManifest($fresh, $this->config->get('pharaonic.modulator.disabled', []));
        $diagnostics = array_merge(
            $registry->diagnostics(),
            $this->cache($fresh),
            $this->providers($registry),
            $this->publishedCopies($registry),
            $this->configurationCache()
        );

        return ['registry' => $registry, 'diagnostics' => $diagnostics];
    }

    /**
     * @param  array{sources: list<mixed>, classmap: array<string, array<string, string>>}  $fresh
     * @return list<Diagnostic>
     */
    private function cache(array $fresh): array
    {
        $loader = $this->autoloader->loader();
        $authoritative = $loader !== null && $loader->isClassMapAuthoritative();

        if (! $this->manifest->isCacheEnabled()) {
            return $this->app->environment() === 'production'
                ? [Diagnostic::warning('Discovery caching is off (pharaonic.modulator.discovery.cache) in production: every request scans the module pools.')]
                : [];
        }

        $cached = $this->manifest->cached();
        $diagnostics = [];

        if ($cached === null) {
            $diagnostics[] = $authoritative
                ? Diagnostic::error('The module manifest is not cached and Composer\'s class map is authoritative. Run "php artisan module:cache" after "composer dump-autoload -a".')
                : Diagnostic::warning('The module manifest is not cached: modules are discovered on every request. Run "php artisan module:cache" when deploying.');

            if (! is_writable(dirname($this->manifest->path()))) {
                $diagnostics[] = Diagnostic::warning(sprintf('[%s] is not writable, so the module manifest cannot be cached.', dirname($this->manifest->path())));
            }
        } elseif ($cached['sources'] !== $fresh['sources'] || $cached['classmap'] !== $fresh['classmap']) {
            $message = 'The cached module manifest is out of date (modules or classes changed). Run "php artisan module:cache".';
            $diagnostics[] = $authoritative
                ? Diagnostic::error($message.' Composer\'s class map is authoritative, so new classes do not load until then.')
                : Diagnostic::warning($message);
        }

        return $diagnostics;
    }

    /**
     * Only Providers/<Name>ServiceProvider is registered: report the others,
     * and a conventional file that does not declare its class.
     *
     * @return list<Diagnostic>
     */
    private function providers(ModuleRegistry $registry): array
    {
        $diagnostics = [];

        foreach ($registry->enabled() as $module) {
            if ($module->source()->legacy) {
                continue;
            }

            $conventional = $module->name().'ServiceProvider';

            foreach (glob($module->path('app/Providers').DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                $class = $module->namespace().'\\Providers\\'.$name;
                $isProvider = class_exists($class) && is_subclass_of($class, ServiceProvider::class);

                if ($name === $conventional && ! $isProvider) {
                    $diagnostics[] = Diagnostic::error(sprintf('[%s] does not declare the service provider [%s].', $file, $class), $module->id());
                } elseif ($name !== $conventional && $isProvider) {
                    $diagnostics[] = Diagnostic::warning(sprintf('[%s] is not registered automatically: only [%s] is. Register it from that provider.', $class, $conventional), $module->id());
                }
            }
        }

        return $diagnostics;
    }

    /**
     * Local copies made by module:publish whose package changed since: their
     * module.lock records the package version and the hash of every copied file.
     *
     * @return list<Diagnostic>
     */
    private function publishedCopies(ModuleRegistry $registry): array
    {
        $diagnostics = [];

        foreach ($registry->all() as $id => $module) {
            $source = $module->source();

            if ($source->package !== null) {
                continue;
            }

            try {
                $lock = ModuleLock::read($source->path);
            } catch (RuntimeException $e) {
                $diagnostics[] = Diagnostic::warning($e->getMessage(), $id);

                continue;
            }

            if ($lock === null) {
                continue;
            }

            $package = null;

            foreach ($registry->sources($id) as $candidate) {
                if ($candidate->package === $lock['package']) {
                    $package = $candidate;
                }
            }

            if ($package === null) {
                $diagnostics[] = Diagnostic::warning(sprintf('[%s] was published from [%s], which no longer provides it. The local copy is now the only one; delete its %s to stop this check.', $id, $lock['package'], ModuleLock::FILE), $id);

                continue;
            }

            $changes = ModuleLock::compare(
                $lock,
                ModuleLock::hashes($source->path, $lock['children']),
                ModuleLock::hashes($package->path, $lock['children'])
            );

            if ($changes['package'] === [] && $changes['both'] === []) {
                continue;
            }

            $installed = ModuleLock::installed($this->app->basePath('vendor'), $lock['package']);
            $message = sprintf(
                '[%s] was published from %s %s, and the package (now %s) changed since: %s.',
                $id,
                $lock['package'],
                $lock['version'] ?? '(unknown version)',
                $installed['version'] ?? 'unknown',
                $this->list(array_merge($changes['package'], $changes['both']))
            );

            if ($changes['both'] !== []) {
                $message .= sprintf(' Changed in both the package and your copy: %s.', $this->list($changes['both']));
            }

            $diagnostics[] = Diagnostic::warning($message.sprintf(' Compare them, then bring the changes into your copy, or run "php artisan module:publish %s --force" to take the package version (your copy is kept as a backup).', $id), $id);
        }

        return $diagnostics;
    }

    /**
     * @param  list<string>  $files
     */
    private function list(array $files): string
    {
        sort($files, SORT_STRING);
        $shown = array_slice($files, 0, 10);

        return implode(', ', $shown).(count($files) > 10 ? sprintf(' and %d more', count($files) - 10) : '');
    }

    /**
     * @return list<Diagnostic>
     */
    private function configurationCache(): array
    {
        $file = $this->app->configPath('pharaonic'.DIRECTORY_SEPARATOR.'modulator.php');
        $cached = $this->app->bootstrapPath('cache'.DIRECTORY_SEPARATOR.'config.php');

        if ($this->app instanceof FoundationApplication && $this->app->configurationIsCached() && is_file($file) && is_file($cached) && filemtime($file) > filemtime($cached)) {
            return [Diagnostic::warning('config/pharaonic/modulator.php changed after "config:cache": the application still uses the cached module states. Run "php artisan config:cache".')];
        }

        return [];
    }
}
