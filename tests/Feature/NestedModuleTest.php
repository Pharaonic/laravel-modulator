<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class NestedModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Group "Store" (no app/), parent module "Store/Checkout", child and grandchild.
        $this->write('Modules/Store/Catalog/app/Item.php', '<?php namespace Modules\\Store\\Catalog; class Item {}');
        $this->writeProvider('Modules/Store/Checkout', 'Modules\\Store\\Checkout', 'Checkout');
        $this->write('Modules/Store/Checkout/app/Cart.php', '<?php namespace Modules\\Store\\Checkout; class Cart {}');
        $this->writeProvider('Modules/Store/Checkout/Modules/Payments', 'Modules\\Store\\Checkout\\Payments', 'Payments');
        $this->write('Modules/Store/Checkout/Modules/Payments/app/Gateway.php', '<?php namespace Modules\\Store\\Checkout\\Payments; class Gateway {}');
        $this->write('Modules/Store/Checkout/Modules/Payments/Modules/Stripe/app/Client.php', '<?php namespace Modules\\Store\\Checkout\\Payments\\Stripe; class Client {}');
        $this->write('Modules/Store/Checkout/Modules/Payments/resources/views/pay.blade.php', 'pay');
    }

    public function test_identities_namespaces_and_files_map_at_every_depth(): void
    {
        $this->reload();

        $this->assertSame(['Store/Catalog', 'Store/Checkout', 'Store/Checkout/Payments', 'Store/Checkout/Payments/Stripe'], array_keys(Modules::all()));
        $this->assertNull(Modules::find('Store'), 'A namespace group is not a module.');

        $this->assertSame(realpath($this->base.'/Modules/Store/Catalog/app/Item.php'), (new \ReflectionClass('Modules\\Store\\Catalog\\Item'))->getFileName());
        $this->assertSame(realpath($this->base.'/Modules/Store/Checkout/app/Cart.php'), (new \ReflectionClass('Modules\\Store\\Checkout\\Cart'))->getFileName());
        $this->assertSame(realpath($this->base.'/Modules/Store/Checkout/Modules/Payments/app/Gateway.php'), (new \ReflectionClass('Modules\\Store\\Checkout\\Payments\\Gateway'))->getFileName());
        $this->assertSame(realpath($this->base.'/Modules/Store/Checkout/Modules/Payments/Modules/Stripe/app/Client.php'), (new \ReflectionClass('Modules\\Store\\Checkout\\Payments\\Stripe\\Client'))->getFileName());

        $payments = Modules::find('Store/Checkout/Payments');
        $this->assertSame('Store/Checkout', $payments->parent());
        $this->assertSame(['Store/Checkout/Payments/Stripe'], $payments->children());
        $this->assertSame('store-checkout-payments', $payments->slug());
        $this->assertSame('pay', view('store-checkout-payments::pay')->render());
        $this->assertTrue($this->app->make('Modules\\Store\\Checkout\\Payments.booted'));
    }

    public function test_a_disabled_parent_disables_its_descendants_and_keeps_their_state(): void
    {
        $this->reload(['disabled' => ['Store/Checkout']]);

        $this->assertFalse(Modules::find('Store/Checkout/Payments')->isEnabled());
        $this->assertFalse(Modules::find('Store/Checkout/Payments/Stripe')->isEnabled());
        $this->assertSame('Disabled because its parent [Store/Checkout] is disabled.', Modules::find('Store/Checkout/Payments')->disabledReason());
        $this->assertTrue(Modules::find('Store/Catalog')->isEnabled(), 'A sibling under the group is not affected.');
        $this->assertFalse($this->app->bound('Modules\\Store\\Checkout\\Payments.registered'));

        $this->reload(['disabled' => []]);

        $this->assertTrue(Modules::find('Store/Checkout/Payments/Stripe')->isEnabled());
    }

    public function test_a_child_namespace_cannot_overlap_a_folder_of_its_parent(): void
    {
        $this->write('Modules/Store/Checkout/app/Payments/Legacy.php', '<?php namespace Modules\\Store\\Checkout\\Payments; class Legacy {}');

        $this->reload();

        $messages = array_map(static function ($diagnostic) {
            return $diagnostic->message;
        }, $this->registry()->diagnostics());

        $this->assertNotEmpty(preg_grep('/shares its namespace \[Modules\\\\Store\\\\Checkout\\\\Payments\\\\\]/', $messages));
    }
}
