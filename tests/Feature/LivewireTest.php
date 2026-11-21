<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Illuminate\Foundation\Application;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\LivewireComponents;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class LivewireTest extends TestCase
{
    private function livewireComponent(string $module, string $namespace, string $class, string $html): void
    {
        $position = strrpos($class, '\\');
        $short = $position === false ? $class : substr($class, $position + 1);
        $namespace = trim($namespace.'\\Livewire\\'.($position === false ? '' : substr($class, 0, $position)), '\\');

        $this->write($module.'/app/Livewire/'.str_replace('\\', '/', $class).'.php', <<<PHP
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

    /**
     * The class registered under a component name, or null.
     */
    private function registered(string $name): ?string
    {
        try {
            return $this->livewire4()
                ? app('livewire.finder')->resolveClassComponentClassName($name)
                : app('Livewire\\Mechanisms\\ComponentRegistry')->getClass($name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Livewire 4 resolves "<namespace>::<name>" from a class namespace, on demand.
     */
    private function livewire4(): bool
    {
        return method_exists(app('livewire'), 'addNamespace');
    }

    private function render(string $blade): string
    {
        $file = $this->write('resources/views/livewire-probe-'.md5($blade).'.blade.php', $blade);

        return view()->file($file)->render();
    }

    public function test_module_components_are_registered_under_the_module_slug(): void
    {
        $this->requiresPackage('Livewire\\Component', 'livewire/livewire');
        $this->livewireComponent('Modules/Shop', 'Modules\\Shop', 'Cart', 'shop cart');
        $this->livewireComponent('Modules/Shop', 'Modules\\Shop', 'Admin\\UserTable', 'user table');
        $this->livewireComponent('Modules/Shop/Modules/Orders', 'Modules\\Shop\\Orders', 'OrderList', 'order list');
        $this->write('Modules/Shop/app/Livewire/Base.php', '<?php namespace Modules\\Shop\\Livewire; abstract class Base extends \\Livewire\\Component {}');
        $this->write('Modules/Shop/app/Livewire/Helper.php', '<?php namespace Modules\\Shop\\Livewire; class Helper {}');
        $this->reload();

        $this->assertStringContainsString('shop cart', $this->render('<livewire:shop::cart />'));
        $this->assertStringContainsString('user table', $this->render("@livewire('shop::admin.user-table')"));
        $this->assertStringContainsString('order list', $this->render('<livewire:shop-orders::order-list />'));

        $this->assertSame('Modules\\Shop\\Livewire\\Cart', $this->registered('shop::cart'));

        if (! $this->livewire4()) {
            $this->assertNull($this->registered('shop::base'), 'An abstract class is not a component.');
            $this->assertNull($this->registered('shop::helper'), 'A class that is not a component is skipped.');
        }
    }

    public function test_components_of_a_disabled_module_are_not_registered(): void
    {
        $this->requiresPackage('Livewire\\Component', 'livewire/livewire');
        $this->livewireComponent('Modules/Shop', 'Modules\\Shop', 'Cart', 'shop cart');
        $this->reload(['disabled' => ['Shop']]);

        $this->assertNull($this->registered('shop::cart'));
    }

    public function test_nothing_is_registered_without_livewire(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $app = new Application($this->base);
        LivewireComponents::register($app, Modules::find('Shop'), ['Modules\\Shop\\Livewire\\Cart']);

        $this->assertFalse($app->bound('livewire'));
    }

    public function test_the_generator_writes_a_registered_component_its_view_and_its_test(): void
    {
        $this->requiresPackage('Livewire\\Component', 'livewire/livewire');
        $this->directory('Modules/Shop/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:livewire', ['module' => 'Shop', 'name' => 'Admin/StockTable', '--test' => true, '--phpunit' => true]);
        $this->assertSame(0, $status, $output);

        $class = (string) file_get_contents($this->base.'/Modules/Shop/app/Livewire/Admin/StockTable.php');
        $this->assertStringContainsString('namespace Modules\\Shop\\Livewire\\Admin;', $class);
        $this->assertStringContainsString("view('shop::livewire.admin.stock-table')", $class);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/livewire/admin/stock-table.blade.php');

        $test = (string) file_get_contents($this->base.'/Modules/Shop/tests/Feature/Livewire/Admin/StockTableTest.php');
        $this->assertStringContainsString('namespace Modules\\Shop\\Tests\\Feature\\Livewire\\Admin;', $test);
        $this->assertStringContainsString('use Modules\\Shop\\Livewire\\Admin\\StockTable;', $test);
        $this->assertStringContainsString('Livewire::test(StockTable::class)', $test);

        $this->write('Modules/Shop/resources/views/livewire/admin/stock-table.blade.php', '<div>generated table</div>');
        $this->reload();
        $this->assertStringContainsString('generated table', $this->render('<livewire:shop::admin.stock-table />'));

        $this->write('vendor/bin/pest', '<?php');
        [$status, $output] = $this->command('module:make:livewire', ['module' => 'Shop', 'name' => 'Counter', '--inline' => true, '--pest' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString("<<<'blade'", (string) file_get_contents($this->base.'/Modules/Shop/app/Livewire/Counter.php'));
        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/resources/views/livewire/counter.blade.php');
        $pest = (string) file_get_contents($this->base.'/Modules/Shop/tests/Feature/Livewire/CounterTest.php');
        $this->assertStringContainsString('uses(Tests\\TestCase::class);', $pest);
        $this->assertStringContainsString('Livewire::test(Counter::class)', $pest);
    }

    public function test_without_livewire_the_generator_refuses_and_writes_nothing(): void
    {
        $this->withoutPackage('Livewire\\Component', 'livewire/livewire');
        $this->directory('Modules/Shop/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:livewire', ['module' => 'Shop', 'name' => 'Cart']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('composer require livewire/livewire', $output);
        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/app/Livewire/Cart.php');
    }
}
