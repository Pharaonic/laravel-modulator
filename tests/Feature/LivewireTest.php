<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Illuminate\Foundation\Application;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\LivewireComponents;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class LivewireTest extends TestCase
{
    private function component(string $module, string $namespace, string $class, string $html): void
    {
        $position = strrpos($class, '\\');
        $short = $position === false ? $class : substr($class, $position + 1);
        $namespace = trim($namespace.'\\Http\\Livewire\\'.($position === false ? '' : substr($class, 0, $position)), '\\');

        $this->write($module.'/app/Http/Livewire/'.str_replace('\\', '/', $class).'.php', <<<PHP
<?php

namespace {$namespace};

use Livewire\\Component;

class {$short} extends Component
{
    public function render()
    {
        return '<div>{$html}</div>';
    }
}
PHP);
    }

    private function render(string $blade): string
    {
        $file = $this->write('resources/views/livewire-probe-'.md5($blade).'.blade.php', $blade);

        return view()->file($file)->render();
    }

    public function test_module_components_are_registered_under_the_module_slug(): void
    {
        $this->component('Modules/Shop', 'Modules\\Shop', 'Cart', 'shop cart');
        $this->component('Modules/Shop', 'Modules\\Shop', 'Admin\\UserTable', 'user table');
        $this->component('Modules/Shop/Modules/Orders', 'Modules\\Shop\\Orders', 'OrderList', 'order list');
        $this->write('Modules/Shop/app/Http/Livewire/Base.php', '<?php namespace Modules\\Shop\\Http\\Livewire; abstract class Base extends \\Livewire\\Component {}');
        $this->write('Modules/Shop/app/Http/Livewire/Helper.php', '<?php namespace Modules\\Shop\\Http\\Livewire; class Helper {}');
        $this->reload();

        $this->assertStringContainsString('shop cart', $this->render('<livewire:shop::cart />'));
        $this->assertStringContainsString('user table', $this->render("@livewire('shop::admin.user-table')"));
        $this->assertStringContainsString('order list', $this->render('<livewire:shop-orders::order-list />'));

        $aliases = app('livewire')->getComponentAliases();
        $this->assertSame('Modules\\Shop\\Http\\Livewire\\Cart', $aliases['shop::cart'] ?? null);
        $this->assertArrayNotHasKey('shop::base', $aliases, 'An abstract class is not a component.');
        $this->assertArrayNotHasKey('shop::helper', $aliases, 'A class that is not a component is skipped.');
    }

    public function test_components_of_a_disabled_module_are_not_registered(): void
    {
        $this->component('Modules/Shop', 'Modules\\Shop', 'Cart', 'shop cart');
        $this->reload(['disabled' => ['Shop']]);

        $this->assertArrayNotHasKey('shop::cart', app('livewire')->getComponentAliases());
    }

    public function test_nothing_is_registered_without_livewire(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $app = new Application($this->base);
        LivewireComponents::register($app, Modules::find('Shop'), ['Modules\\Shop\\Http\\Livewire\\Cart']);

        $this->assertFalse($app->bound('livewire'));
    }

    public function test_the_generator_writes_a_registered_component_its_view_and_its_test(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:livewire', ['module' => 'Shop', 'name' => 'Admin/StockTable', '--test' => true, '--phpunit' => true]);
        $this->assertSame(0, $status, $output);

        $class = (string) file_get_contents($this->base.'/Modules/Shop/app/Http/Livewire/Admin/StockTable.php');
        $this->assertStringContainsString('namespace Modules\\Shop\\Http\\Livewire\\Admin;', $class);
        $this->assertStringContainsString("view('shop::livewire.admin.stock-table')", $class);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/livewire/admin/stock-table.blade.php');

        $test = (string) file_get_contents($this->base.'/Modules/Shop/tests/Feature/Http/Livewire/Admin/StockTableTest.php');
        $this->assertStringContainsString('namespace Modules\\Shop\\Tests\\Feature\\Http\\Livewire\\Admin;', $test);
        $this->assertStringContainsString('use Modules\\Shop\\Http\\Livewire\\Admin\\StockTable;', $test);
        $this->assertStringContainsString('Livewire::test(StockTable::class)', $test);

        $this->write('Modules/Shop/resources/views/livewire/admin/stock-table.blade.php', '<div>generated table</div>');
        $this->reload();
        $this->assertStringContainsString('generated table', $this->render('<livewire:shop::admin.stock-table />'));

        $this->write('vendor/bin/pest', '<?php');
        [$status, $output] = $this->command('module:make:livewire', ['module' => 'Shop', 'name' => 'Counter', '--inline' => true, '--pest' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString("<<<'blade'", (string) file_get_contents($this->base.'/Modules/Shop/app/Http/Livewire/Counter.php'));
        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/resources/views/livewire/counter.blade.php');
        $pest = (string) file_get_contents($this->base.'/Modules/Shop/tests/Feature/Http/Livewire/CounterTest.php');
        $this->assertStringContainsString('uses(Tests\\TestCase::class);', $pest);
        $this->assertStringContainsString('Livewire::test(Counter::class)', $pest);
    }
}
