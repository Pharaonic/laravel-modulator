<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Illuminate\Console\Events\CommandFinished;
use Pharaonic\Laravel\Modulator\Discovery\Manifest;
use Pharaonic\Laravel\Modulator\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ArchitectureCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => [[
            'name' => 'acme/shop',
            'install-path' => '../acme/shop',
            'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => 'modules']]]]],
        ]]]));
        $this->directory('vendor/acme/shop/modules/Shop/app');
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/Modules/Orders/app');
        $this->directory('Modules/Catalog/app');
        $this->define('Modules/Shop', ['dependencies' => ['Catalog', 'Search']]);
        $this->reload();
    }

    public function test_list_and_pools(): void
    {
        [$status, $output] = $this->command('module:list');
        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/Shop\/Orders\s+\|\s+disabled\s+\|\s+local\s+\|\s+-\s+\|\s+Modules\/Shop\/Modules\/Orders/', $output);

        [$status, $output] = $this->command('module:pools');
        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/local\s+\|\s+100.*\n.*app\s+\|\s+90.*\n.*acme\/shop\s+\|\s+50/', $output);
    }

    public function test_source_explains_the_winner_and_the_shadowed_copies(): void
    {
        [$status, $output] = $this->command('module:source', ['module' => 'Shop']);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/active\s+\|\s+local/', $output);
        $this->assertMatchesRegularExpression('/shadowed\s+\|\s+acme\/shop\s+\|\s+50\s+\|\s+acme\/shop/', $output);
        $this->assertStringContainsString('highest priority (100)', $output);
        $this->assertStringContainsString('dependency [Search] is missing', $output);

        $this->assertSame(1, $this->command('module:source', ['module' => 'Missing'])[0]);
    }

    public function test_graph_as_text_and_json(): void
    {
        [$status, $output] = $this->command('module:graph');
        $this->assertSame(0, $status);
        $this->assertStringContainsString('Shop [disabled] -> needs Catalog, Search (missing)', $output);
        $this->assertStringContainsString('└─ Shop/Orders [disabled]', $output);

        [$status, $output] = $this->command('module:graph', ['--format' => 'json']);
        $graph = json_decode($output, true);
        $this->assertSame(0, $status);
        $this->assertContains(['from' => 'Shop/Orders', 'to' => 'Shop', 'type' => 'parent'], $graph['edges']);
        $this->assertContains(['from' => 'Shop', 'to' => 'Search', 'type' => 'dependency', 'missing' => true], $graph['edges']);

        $this->assertSame(1, $this->command('module:graph', ['--format' => 'dot'])[0]);
    }

    public function test_doctor_reports_errors_warnings_and_succeeds_when_clean(): void
    {
        $this->write('Modules/Catalog/app/Providers/SearchServiceProvider.php', '<?php namespace Modules\\Catalog\\Providers; class SearchServiceProvider extends \\Illuminate\\Support\\ServiceProvider {}');
        $this->reload(['discovery' => ['cache' => true]]);
        $this->app->make(Manifest::class)->clear();

        [$status, $output] = $this->command('module:doctor');

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('dependency [Search] is missing', $output);
        $this->assertStringContainsString('[Modules\\Catalog\\Providers\\SearchServiceProvider] is not registered automatically', $output);
        $this->assertStringContainsString('not cached', $output);

        $this->define('Modules/Shop', ['dependencies' => ['Catalog']]);
        unlink($this->base.'/Modules/Catalog/app/Providers/SearchServiceProvider.php');
        $this->command('module:cache');
        $this->reload(['discovery' => ['cache' => true]]);

        [$status, $output] = $this->command('module:doctor');
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('No problems found.', $output);

        // A class added after module:cache.
        $this->write('Modules/Catalog/app/Models/Product.php', '<?php namespace Modules\\Catalog\\Models; class Product {}');
        [$status, $output] = $this->command('module:doctor');
        $this->assertStringContainsString('out of date', $output);
    }

    public function test_check_enforces_dependencies_exposed_namespaces_and_hierarchy(): void
    {
        $this->define('Modules/Catalog', ['exposes' => ['Contracts']]);
        $this->define('Modules/Shop', ['dependencies' => ['Catalog']]);
        $this->write('Modules/Catalog/app/Contracts/Searchable.php', '<?php namespace Modules\\Catalog\\Contracts; interface Searchable {}');
        $this->write('Modules/Catalog/app/Models/Product.php', '<?php namespace Modules\\Catalog\\Models; class Product {}');
        $this->write('Modules/Shop/app/Cart.php', <<<'PHP'
<?php

namespace Modules\Shop;

use Modules\Catalog\Contracts\Searchable;
use Modules\Catalog\Models\Product;
use Modules\Shop\Orders\Order;

class Cart implements Searchable
{
}
PHP);
        $this->write('Modules/Shop/Modules/Orders/app/Order.php', '<?php namespace Modules\\Shop\\Orders; class Order extends \\Modules\\Shop\\Cart {}');
        $this->directory('Modules/Blog/app');
        $this->write('Modules/Blog/app/Post.php', '<?php namespace Modules\\Blog; class Post { public function tags() { return \\Modules\\Catalog\\Contracts\\Searchable::class; } }');
        $this->reload();

        [$status, $output] = $this->command('module:check', ['--json' => true]);
        $violations = json_decode($output, true);

        $this->assertSame(1, $status);
        $this->assertSame([
            ['Blog', 'Modules\\Catalog\\Contracts\\Searchable', 'without declaring it'],
            ['Shop', 'Modules\\Catalog\\Models\\Product', 'internal to [Catalog]'],
            ['Shop', 'Modules\\Shop\\Orders\\Order', 'of its child module [Shop/Orders]'],
        ], array_map(static function (array $violation): array {
            preg_match('/without declaring it|internal to \[Catalog\]|of its child module \[Shop\/Orders\]/', $violation['message'], $match);

            return [$violation['module'], $violation['class'], $match[0]];
        }, $violations));

        [$status, $output] = $this->command('module:check', ['module' => 'Shop/Orders']);
        $this->assertSame(0, $status, 'A child may use its parent.');
        $this->assertStringContainsString('No boundary violations found.', $output);

        [$status, $output] = $this->command('module:check', ['module' => 'Shop']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Modules/Shop/app/Cart.php:6', $output);
    }

    public function test_optimize_caches_and_optimize_clear_clears_the_manifest(): void
    {
        $manifest = $this->app->make(Manifest::class);
        $output = new BufferedOutput;

        $this->app['events']->dispatch(new CommandFinished('optimize', new ArrayInput([]), $output, 0));
        $this->assertFileExists($manifest->path());
        $this->assertStringContainsString('Module manifest cached.', $output->fetch());

        $this->app['events']->dispatch(new CommandFinished('optimize:clear', new ArrayInput([]), $output, 0));
        $this->assertFileDoesNotExist($manifest->path());

        $this->app['events']->dispatch(new CommandFinished('optimize', new ArrayInput([]), $output, 1));
        $this->assertFileDoesNotExist($manifest->path(), 'A failed optimize does not cache.');
    }
}
