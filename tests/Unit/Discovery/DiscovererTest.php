<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Discovery;

use Pharaonic\Laravel\Modulator\DirectoryPool;
use Pharaonic\Laravel\Modulator\Discovery\Discoverer;
use Pharaonic\Laravel\Modulator\Discovery\Source;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class DiscovererTest extends FilesystemTestCase
{
    /**
     * @return array<string, Source>
     */
    private function discover(DirectoryPool ...$pools): array
    {
        $result = (new Discoverer)->discover(array_values($pools));
        $sources = [];

        foreach ($result['sources'] as $source) {
            $sources[$source->id] = $source;
        }

        return $sources;
    }

    private function pool(string $name = 'local', string $namespace = 'Modules', bool $legacy = false, string $directory = 'Modules'): DirectoryPool
    {
        return new DirectoryPool($name, $this->base.'/'.$directory, $namespace, 100, null, $legacy);
    }

    public function test_a_directory_with_app_is_a_module_and_needs_no_manifest(): void
    {
        $this->directory('Modules/Shop/app');

        $sources = $this->discover($this->pool());

        $this->assertSame(['Shop'], array_keys($sources));
        $this->assertSame('Modules\\Shop', $sources['Shop']->namespace);
        $this->assertSame(realpath($this->base.'/Modules/Shop'), $sources['Shop']->path);
        $this->assertSame(realpath($this->base.'/Modules/Shop').DIRECTORY_SEPARATOR.'app', $sources['Shop']->classPath());
    }

    public function test_groups_and_child_modules_map_to_namespaces_at_every_depth(): void
    {
        $this->directory('Modules/Commerce/Catalog/app');                       // group Commerce, module Commerce/Catalog
        $this->directory('Modules/Commerce/Checkout/app');                      // module Commerce/Checkout
        $this->directory('Modules/Commerce/Checkout/Modules/Payments/app');     // child of Checkout
        $this->directory('Modules/Commerce/Checkout/Modules/Payments/Modules/Stripe/app');
        $this->directory('Modules/Commerce/Checkout/Modules/Wallets/Paypal/app'); // group inside a module's Modules/
        $this->directory('Modules/Commerce/Checkout/resources/views');          // not traversed: inside a module

        $sources = $this->discover($this->pool());

        $this->assertSame([
            'Commerce/Catalog',
            'Commerce/Checkout',
            'Commerce/Checkout/Payments',
            'Commerce/Checkout/Payments/Stripe',
            'Commerce/Checkout/Wallets/Paypal',
        ], array_keys($sources));
        $this->assertArrayNotHasKey('Commerce', $sources);
        $this->assertSame('Modules\\Commerce\\Checkout\\Payments\\Stripe', $sources['Commerce/Checkout/Payments/Stripe']->namespace);
        $this->assertSame('Modules\\Commerce\\Checkout\\Wallets\\Paypal', $sources['Commerce/Checkout/Wallets/Paypal']->namespace);
    }

    public function test_the_same_identity_twice_in_one_pool_is_an_error(): void
    {
        $this->directory('Modules/Commerce/Checkout/app');
        $this->directory('Modules/Commerce/Modules/Checkout/app');

        $result = (new Discoverer)->discover([$this->pool()]);

        $this->assertCount(1, $result['sources']);
        $this->assertStringContainsString('exists twice in pool [local]', $result['diagnostics'][0]->message);
    }

    public function test_invalid_directory_names_are_reported_only_when_they_look_like_modules(): void
    {
        $this->directory('Modules/shop/app');
        $this->directory('Modules/node_modules/something');
        $this->directory('Modules/.git/app');

        $result = (new Discoverer)->discover([$this->pool()]);

        $this->assertSame([], $result['sources']);
        $this->assertCount(1, $result['diagnostics']);
        $this->assertStringContainsString('[shop] is not a valid identity segment', $result['diagnostics'][0]->message);
    }

    public function test_module_definitions_declare_dependencies_and_exposed_namespaces(): void
    {
        $this->write('Modules/Orders/app/.keep');
        $this->write('Modules/Orders/module.php', "<?php return ['dependencies' => ['Catalog', 'Commerce/Checkout'], 'exposes' => ['Contracts', 'Events\\\\Public']];");
        $this->write('Modules/Broken/app/.keep');
        $this->write('Modules/Broken/module.php', "<?php return ['dependencies' => ['../x', 'Broken'], 'extra' => true];");

        $result = (new Discoverer)->discover([$this->pool()]);
        $sources = [];

        foreach ($result['sources'] as $source) {
            $sources[$source->id] = $source;
        }

        $this->assertSame(['Catalog', 'Commerce/Checkout'], $sources['Orders']->dependencies);
        $this->assertSame(['Contracts', 'Events\\Public'], $sources['Orders']->exposes);
        $this->assertSame([], $sources['Broken']->dependencies);

        $messages = array_map(static function ($diagnostic): string {
            return $diagnostic->message;
        }, $result['diagnostics']);

        $this->assertCount(3, $messages);
        $this->assertStringContainsString('Unknown key [extra]', $messages[0]);
        $this->assertStringContainsString('Invalid entry [../x]', $messages[1]);
        $this->assertStringContainsString('Invalid entry [Broken]', $messages[2]);
    }

    public function test_legacy_pools_use_the_1x_providers_rule(): void
    {
        $this->directory('app/Modules/Shop/Providers');
        $this->directory('app/Modules/Blog/Posts/Providers');
        $this->write('app/Modules/ServiceProvider.php', '<?php');

        $sources = $this->discover($this->pool('app', 'App\\Modules', true, 'app/Modules'));

        $this->assertSame(['Blog/Posts', 'Shop'], array_keys($sources));
        $this->assertTrue($sources['Shop']->legacy);
        $this->assertSame('App\\Modules\\Blog\\Posts', $sources['Blog/Posts']->namespace);
        $this->assertSame([], $sources['Shop']->prefixes());
        $this->assertSame($sources['Shop']->path, $sources['Shop']->classPath());
    }

    public function test_symlink_loops_and_missing_pools_are_harmless(): void
    {
        $this->directory('Modules/Group');

        if (! @symlink($this->base.'/Modules', $this->base.'/Modules/Group/Loop')) {
            $this->markTestSkipped('Symlinks are not available.');
        }

        $result = (new Discoverer)->discover([$this->pool(), new DirectoryPool('missing', $this->base.'/nowhere')]);

        $this->assertSame([], $result['sources']);
        $this->assertContains($this->base.'/nowhere', $result['watch']);
    }

    public function test_discovery_order_never_depends_on_the_filesystem(): void
    {
        foreach (['Zeta', 'Alpha', 'Mid'] as $name) {
            $this->directory('Modules/'.$name.'/app');
        }

        $this->assertSame(['Alpha', 'Mid', 'Zeta'], array_keys($this->discover($this->pool())));
    }
}
