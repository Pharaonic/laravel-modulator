<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Illuminate\Support\Facades\Route;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class DevelopmentCommandsTest extends TestCase
{
    public function test_module_test_runs_the_module_tests_and_returns_their_exit_code(): void
    {
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/tests');
        $this->write('vendor/bin/phpunit', "<?php\nfile_put_contents(__DIR__.'/arguments.json', json_encode(array_slice(\$argv, 1)));\nexit(3);\n");
        $this->reload();

        [$status] = $this->command('module:test', ['module' => 'Shop', '--filter' => 'a; rm -rf / $(id)', '--stop-on-failure' => true]);

        $this->assertSame(3, $status);
        $this->assertSame(
            [realpath($this->base.'/Modules/Shop').DIRECTORY_SEPARATOR.'tests', '--filter=a; rm -rf / $(id)', '--stop-on-failure'],
            json_decode((string) file_get_contents($this->base.'/vendor/bin/arguments.json'), true)
        );

        [$status, $output] = $this->command('module:test', ['module' => 'Shop', '--parallel' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('need Pest', $output);
    }

    public function test_module_test_needs_a_tests_directory_and_a_valid_identity(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $this->assertSame(1, $this->command('module:test', ['module' => 'Shop'])[0]);
        $this->assertSame(1, $this->command('module:test', ['module' => 'Shop/../../etc'])[0]);
    }

    public function test_routes_lists_the_routes_each_module_owns(): void
    {
        $this->write('Modules/Shop/routes/web.php', "<?php Illuminate\\Support\\Facades\\Route::get('shop', function () { return 'shop'; })->name('shop.index');");
        $this->write('Modules/Shop/Modules/Orders/routes/api.php', "<?php Illuminate\\Support\\Facades\\Route::get('orders', function () { return []; });");
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/Modules/Orders/app');
        $this->directory('app/Modules/Blog/Providers');
        $this->reload();

        Route::get('not-shop', function () {
            return '';
        })->name('shop.fake');
        Route::get('blog', 'App\\Modules\\Blog\\Http\\Controllers\\PostController@index');

        [$status, $output] = $this->command('module:routes', ['--json' => true]);
        $routes = json_decode(substr($output, (int) strpos($output, '[')), true);

        $this->assertSame(0, $status);
        $this->assertSame([
            ['Blog', 'blog'],
            ['Shop', 'shop'],
            ['Shop/Orders', 'api/orders'],
        ], array_map(static function (array $route): array {
            return [$route['module'], $route['uri']];
        }, $routes));

        [$status, $output] = $this->command('module:routes', ['module' => 'Shop']);
        $this->assertStringContainsString('shop.index', $output);
        $this->assertStringContainsString('api/orders', $output, 'Children are included.');
        $this->assertStringNotContainsString('not-shop', $output, 'A name prefix is not ownership.');
    }
}
