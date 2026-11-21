<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Actions;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Modulator\Autoload\Autoloader;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\ModuleRegistry;
use Pharaonic\Laravel\Modulator\Support\Diagnostic;

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
    ) {
    }

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
     * @return list<Diagnostic>
     */
    private function configurationCache(): array
    {
        $file = $this->app->configPath('pharaonic'.DIRECTORY_SEPARATOR.'modulator.php');
        $cached = $this->app->bootstrapPath('cache'.DIRECTORY_SEPARATOR.'config.php');

        if ($this->app->configurationIsCached() && is_file($file) && is_file($cached) && filemtime($file) > filemtime($cached)) {
            return [Diagnostic::warning('config/pharaonic/modulator.php changed after "config:cache": the application still uses the cached module states. Run "php artisan config:cache".')];
        }

        return [];
    }
}
