<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Discovery;

use Pharaonic\Laravel\Modulator\Discovery\ClassMapBuilder;
use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class ClassMapBuilderTest extends FilesystemTestCase
{
    public function test_it_maps_psr4_classes_of_app_factories_and_seeders(): void
    {
        $product = $this->write('Shop/app/Models/Product.php', "<?php\nnamespace Modules\\Shop\\Models;\n\nfinal class Product {}\n");
        $contract = $this->write('Shop/app/Contracts/Catalog.php', '<?php namespace Modules\\Shop\\Contracts; interface Catalog {}');
        $trait = $this->write('Shop/app/Concerns/HasPrice.php', '<?php namespace Modules\\Shop\\Concerns; trait HasPrice {}');
        $enum = $this->write('Shop/app/Enums/Status.php', "<?php namespace Modules\\Shop\\Enums; enum Status: string { case Draft = 'draft'; }");
        $factory = $this->write('Shop/database/factories/ProductFactory.php', '<?php namespace Modules\\Shop\\Database\\Factories; class ProductFactory {}');
        $seeder = $this->write('Shop/database/seeders/ShopSeeder.php', '<?php namespace Modules\\Shop\\Database\\Seeders; class ShopSeeder {}');
        $this->write('Shop/app/helpers.php', '<?php function shop_helper() {}');
        $uses = $this->write('Shop/app/Support/Uses.php', '<?php namespace Modules\\Shop\\Support; $x = Foo::class; $y = new class {}; final class Uses {}');
        $this->write('Shop/tests/ShopTest.php', '<?php namespace Modules\\Shop\\Tests; class ShopTest {}');

        $result = (new ClassMapBuilder)->build(new Source('Shop', 'local', 100, $this->base.'/Shop', 'Modules\\Shop'));

        $this->assertSame([
            'Modules\\Shop\\Concerns\\HasPrice' => $trait,
            'Modules\\Shop\\Contracts\\Catalog' => $contract,
            'Modules\\Shop\\Database\\Factories\\ProductFactory' => $factory,
            'Modules\\Shop\\Database\\Seeders\\ShopSeeder' => $seeder,
            'Modules\\Shop\\Enums\\Status' => $enum,
            'Modules\\Shop\\Models\\Product' => $product,
            'Modules\\Shop\\Support\\Uses' => $uses,
        ], $result['classes']);
        $this->assertSame([], $result['diagnostics']);
    }

    public function test_files_that_do_not_follow_psr4_are_reported_not_mapped(): void
    {
        $this->write('Shop/app/Models/Product.php', '<?php namespace Modules\\Other\\Models; class Product {}');

        $result = (new ClassMapBuilder)->build(new Source('Shop', 'local', 100, $this->base.'/Shop', 'Modules\\Shop'));

        $this->assertSame([], $result['classes']);
        $this->assertStringContainsString('declares [Modules\\Other\\Models\\Product]', $result['diagnostics'][0]->message);
    }

    public function test_excluded_directories_are_skipped(): void
    {
        $this->write('Shop/app/Orders/Order.php', '<?php namespace Modules\\Shop\\Orders; class Order {}');

        $result = (new ClassMapBuilder)->build(new Source('Shop', 'local', 100, $this->base.'/Shop', 'Modules\\Shop'), [$this->base.'/Shop/app/Orders']);

        $this->assertSame([], $result['classes']);
    }
}
