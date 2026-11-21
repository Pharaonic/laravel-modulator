<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Autoload;

use Pharaonic\Laravel\Modulator\Autoload\Autoloader;
use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class AutoloaderTest extends FilesystemTestCase
{
    /**
     * @param  array<string, string>  $classmap
     * @param  array<string, list<string>>  $psr4
     */
    private function composer(array $classmap, array $psr4): void
    {
        $this->write('vendor/composer/autoload_classmap.php', '<?php return '.var_export($classmap, true).';');
        $this->write('vendor/composer/autoload_psr4.php', '<?php return '.var_export($psr4, true).';');
    }

    public function test_composer_mappings_inside_the_selected_source_are_fine(): void
    {
        $local = new Source('Shop', 'local', 100, $this->directory('Modules/Shop'), 'Modules\\Shop');
        $this->composer(
            ['Modules\\Shop\\Models\\Product' => $this->base.'/Modules/Shop/app/Models/Product.php', 'App\\User' => $this->base.'/app/User.php'],
            ['Modules\\' => [$this->base.'/Modules'], 'App\\' => [$this->base.'/app']]
        );

        $this->assertSame([], (new Autoloader($this->base.'/vendor'))->conflicts([$local], []));
    }

    public function test_package_mappings_of_a_shadowed_module_are_conflicts(): void
    {
        $local = new Source('Shop', 'local', 100, $this->directory('Modules/Shop'), 'Modules\\Shop');
        $package = new Source('Shop', 'acme/shop', 50, $this->directory('vendor/acme/shop/modules/Shop'), 'Modules\\Shop', 'acme/shop');
        $this->composer(
            ['Modules\\Shop\\Models\\Product' => $this->base.'/vendor/acme/shop/modules/Shop/app/Models/Product.php'],
            ['Modules\\Shop\\' => [$this->base.'/vendor/acme/shop/modules/Shop/app'], 'Modules\\' => [$this->base.'/vendor/acme/shop/modules']]
        );

        $conflicts = (new Autoloader($this->base.'/vendor'))->conflicts([$local], [$package]);

        $this->assertCount(2, $conflicts);
        $this->assertTrue($conflicts[0]->isError());
        $this->assertStringContainsString('Composer maps [Modules\\Shop\\Models\\Product]', $conflicts[0]->message);
        $this->assertStringContainsString('Composer maps the namespace [Modules\\Shop\\]', $conflicts[1]->message);
    }

    public function test_a_broader_prefix_reaching_another_module_copy_is_a_warning(): void
    {
        $local = new Source('Shop', 'local', 100, $this->directory('Modules/Shop'), 'Modules\\Shop');
        $this->directory('legacy/Shop');
        $this->composer([], ['Modules\\' => [$this->base.'/legacy']]);

        $conflicts = (new Autoloader($this->base.'/vendor'))->conflicts([$local], []);

        $this->assertCount(1, $conflicts);
        $this->assertFalse($conflicts[0]->isError());
    }

    public function test_child_modules_own_their_namespace(): void
    {
        $parent = new Source('Shop', 'acme/shop', 50, $this->directory('vendor/acme/shop/modules/Shop'), 'Modules\\Shop', 'acme/shop');
        $child = new Source('Shop/Orders', 'local', 100, $this->directory('Modules/Shop/Modules/Orders'), 'Modules\\Shop\\Orders');
        $this->composer(['Modules\\Shop\\Orders\\Order' => $this->base.'/vendor/acme/shop/modules/Shop/Modules/Orders/app/Order.php'], []);

        $conflicts = (new Autoloader($this->base.'/vendor'))->conflicts([$parent, $child], []);

        $this->assertCount(1, $conflicts);
        $this->assertSame('Shop/Orders', $conflicts[0]->module);
    }
}
