<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\Modulator\Console\Development;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Pharaonic\Laravel\Modulator\Concerns\ResolvesModules;
use Pharaonic\Laravel\Modulator\Module;
use Throwable;

final class RoutesCommand extends Command
{
    use ResolvesModules;

    /**
     * @var string
     */
    protected $signature = 'module:routes
                            {module? : Only the routes of this module (and its children)}
                            {--method= : Filter the routes by method}
                            {--c|compact : Only show the module, method, URI and action columns}
                            {--columns=* : The columns to show (module, domain, method, uri, name, action, middleware)}
                            {--sort=module : The column to sort by}
                            {--r|reverse : Reverse the order of the routes}
                            {--json : Output the routes as JSON}';

    /**
     * Every column, in display order.
     */
    private const COLUMNS = ['module', 'domain', 'method', 'uri', 'name', 'action', 'middleware'];

    private const DEFAULT_COLUMNS = ['module', 'method', 'uri', 'name', 'action'];

    private const COMPACT_COLUMNS = ['module', 'method', 'uri', 'action'];

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

        $columns = $this->columns();
        $sort = $this->option('sort');
        $sort = is_string($sort) && $sort !== '' ? strtolower($sort) : 'module';

        if ($columns === null || ! in_array($sort, self::COLUMNS, true)) {
            $this->error(sprintf('Unknown column. Use: %s.', implode(', ', self::COLUMNS)));

            return 1;
        }

        $method = $this->option('method');
        $rows = [];

        /** @var Route $route */
        foreach ($router->getRoutes()->getRoutes() as $route) {
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
                'domain' => (string) $route->getDomain(),
                'method' => implode('|', $methods),
                'uri' => $route->uri(),
                'name' => (string) $route->getName(),
                'action' => ltrim($route->getActionName(), '\\'),
                'middleware' => in_array('middleware', $columns, true) ? $this->middleware($route) : '',
            ];
        }

        usort($rows, static function (array $a, array $b) use ($sort): int {
            return [$a[$sort], $a['module'], $a['uri'], $a['method']] <=> [$b[$sort], $b['module'], $b['uri'], $b['method']];
        });

        if ($this->option('reverse')) {
            $rows = array_reverse($rows);
        }

        $rows = array_map(static function (array $row) use ($columns): array {
            return array_intersect_key($row, array_flip($columns));
        }, $rows);

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        if ($rows === []) {
            $this->line($only === null ? 'No module routes.' : sprintf('Module [%s] has no routes.', $only->id()));

            return 0;
        }

        $this->table(array_map(static function (string $column): string {
            return $column === 'uri' ? 'URI' : ucfirst($column);
        }, $columns), array_map('array_values', $rows));

        return 0;
    }

    /**
     * The route's middleware, with its controller's when the controller can be
     * built; otherwise only the middleware declared on the route.
     */
    private function middleware(Route $route): string
    {
        try {
            $middleware = $route->gatherMiddleware();
        } catch (Throwable $e) {
            $middleware = Arr::wrap($route->middleware());
        }

        return implode(',', array_filter($middleware, 'is_string'));
    }

    /**
     * The columns to show, in display order, or null when one is unknown.
     *
     * @return list<string>|null
     */
    private function columns(): ?array
    {
        $requested = [];

        foreach ((array) $this->option('columns') as $value) {
            if (! is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $column) {
                if (trim($column) !== '') {
                    $requested[] = strtolower(trim($column));
                }
            }
        }

        if ($requested === []) {
            return $this->option('compact') ? self::COMPACT_COLUMNS : self::DEFAULT_COLUMNS;
        }

        if (array_diff($requested, self::COLUMNS) !== []) {
            return null;
        }

        return array_values(array_intersect(self::COLUMNS, $requested));
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
