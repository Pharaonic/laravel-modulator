<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Development;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Module;

final class RoutesCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:routes
                            {module? : Only the routes of this module (and its children)}
                            {--method= : Filter the routes by method}
                            {--json : Output the routes as JSON}';

    /**
     * @var string
     */
    protected $description = 'List the routes that belong to modules';

    public function handle(Router $router): int
    {
        $registry = $this->registry();
        $only = null;
        $argument = $this->argument('module');

        if (is_string($argument) && $argument !== '') {
            $only = $this->resolveModule($argument);

            if ($only === null) {
                return 1;
            }
        }

        $method = $this->option('method');
        $rows = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $owner = $this->owner($route, $registry->all());

            if ($owner === null || ($only !== null && $owner !== $only->id() && ! str_starts_with($owner, $only->id().'/'))) {
                continue;
            }

            $methods = array_values(array_filter($route->methods(), 'is_string'));

            if (is_string($method) && $method !== '' && ! in_array(strtoupper($method), $methods, true)) {
                continue;
            }

            $rows[] = [
                'module' => $owner,
                'method' => implode('|', $methods),
                'uri' => $route->uri(),
                'name' => (string) $route->getName(),
                'action' => ltrim($route->getActionName(), '\\'),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['module'], $a['uri'], $a['method']] <=> [$b['module'], $b['uri'], $b['method']];
        });

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        if ($rows === []) {
            $this->line($only === null ? 'No module routes.' : sprintf('Module [%s] has no routes.', $only->id()));

            return 0;
        }

        $this->table(['Module', 'Method', 'URI', 'Name', 'Action'], array_map('array_values', $rows));

        return 0;
    }

    /**
     * The module a route belongs to: the "module" its route file was loaded
     * with, otherwise the module whose namespace holds its controller.
     *
     * @param  array<string, Module>  $modules
     */
    private function owner(Route $route, array $modules): ?string
    {
        $module = $route->getAction('module');

        if (is_string($module) && isset($modules[$module])) {
            return $module;
        }

        $controller = $route->getAction('controller');

        if (! is_string($controller)) {
            return null;
        }

        $class = ltrim(explode('@', $controller)[0], '\\');
        $owner = null;
        $length = 0;

        foreach ($modules as $id => $candidate) {
            $namespace = $candidate->namespace().'\\';

            if (str_starts_with($class, $namespace) && strlen($namespace) > $length) {
                $owner = $id;
                $length = strlen($namespace);
            }
        }

        return $owner;
    }
}
