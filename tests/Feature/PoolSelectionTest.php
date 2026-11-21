<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\Fixtures\TenantPool;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class PoolSelectionTest extends TestCase
{
    /**
     * Install a fake Composer package "acme/<name>" that declares a pool.
     *
     * @param  array<string, mixed>  $pool
     */
    protected function package(string $name, array $pool = ['path' => 'modules']): string
    {
        $root = $this->directory('vendor/acme/'.$name);
        $file = $this->base.'/vendor/composer/installed.json';
        $installed = is_file($file) ? json_decode((string) file_get_contents($file), true) : ['packages' => []];
        $installed['packages'][] = [
            'name' => 'acme/'.$name,
            'install-path' => '../acme/'.$name,
            'extra' => ['pharaonic' => ['modulator' => ['pools' => [$pool]]]],
        ];
        $this->write('vendor/composer/installed.json', (string) json_encode($installed));

        return $root;
    }

    public function test_a_package_module_is_discovered_without_registering_its_own_provider(): void
    {
        $this->package('crm');
        $this->writeProvider('vendor/acme/crm/modules/Crm', 'Modules\\Crm', 'Crm');
        $this->write('vendor/acme/crm/modules/Crm/app/Contact.php', '<?php namespace Modules\\Crm; class Contact {}');

        $this->reload();

        $crm = Modules::find('Crm');
        $this->assertSame('acme/crm', $crm->pool());
        $this->assertSame('acme/crm', $crm->package());
        $this->assertTrue(class_exists('Modules\\Crm\\Contact'));
        $this->assertTrue($this->app->make('Modules\\Crm.booted'));

        $this->reload(['disabled' => ['Crm']]);

        $this->assertFalse($this->app->bound('Modules\\Crm.registered'), 'Disabling a package module keeps its provider out.');
    }

    public function test_a_package_may_provide_several_and_nested_modules(): void
    {
        $this->package('suite');
        $this->directory('vendor/acme/suite/modules/Hr/app');
        $this->directory('vendor/acme/suite/modules/Hr/Modules/Payroll/app');
        $this->directory('vendor/acme/suite/modules/Office/Docs/app');

        $this->reload();

        $this->assertSame(['Hr', 'Hr/Payroll', 'Office/Docs'], array_keys(Modules::all()));
        $this->assertSame('Hr', Modules::find('Hr/Payroll')->parent());
    }

    public function test_the_local_copy_shadows_the_package_and_a_disabled_local_copy_does_not_fall_back(): void
    {
        $this->package('desk');
        $this->writeProvider('vendor/acme/desk/modules/Desk', 'Modules\\Desk', 'Desk');
        $this->directory('Modules/Desk/app');

        $this->reload();

        $this->assertSame('local', Modules::find('Desk')->pool());
        $this->assertCount(2, $this->registry()->sources('Desk'));
        $this->assertFalse($this->app->bound('Modules\\Desk.registered'));

        $this->reload(['disabled' => ['Desk']]);

        $this->assertSame('local', Modules::find('Desk')->pool());
        $this->assertSame([], Modules::enabled());
        $this->assertFalse($this->app->bound('Modules\\Desk.registered'));
    }

    public function test_a_child_can_come_from_another_pool_than_its_parent(): void
    {
        $this->package('mall');
        $this->write('vendor/acme/mall/modules/Mall/app/Shopfront.php', '<?php namespace Modules\\Mall; class Shopfront {}');
        $this->write('vendor/acme/mall/modules/Mall/Modules/Orders/app/Order.php', '<?php namespace Modules\\Mall\\Orders; class Order { const FROM = "package"; }');
        $this->write('vendor/acme/mall/modules/Mall/Modules/Reviews/app/Review.php', '<?php namespace Modules\\Mall\\Reviews; class Review {}');
        $this->write('Modules/Mall/Modules/Orders/app/Order.php', '<?php namespace Modules\\Mall\\Orders; class Order { const FROM = "local"; }');

        $this->reload();

        $this->assertSame('acme/mall', Modules::find('Mall')->pool());
        $this->assertSame('local', Modules::find('Mall/Orders')->pool());
        $this->assertSame('acme/mall', Modules::find('Mall/Reviews')->pool());
        $this->assertSame('Mall', Modules::find('Mall/Orders')->parent());
        $this->assertSame('local', constant('Modules\\Mall\\Orders\\Order::FROM'));
        $this->assertTrue(class_exists('Modules\\Mall\\Shopfront'));
        $this->assertTrue(class_exists('Modules\\Mall\\Reviews\\Review'));
    }

    public function test_equal_priorities_fail_predictably(): void
    {
        $this->package('one', ['path' => 'modules', 'priority' => 60]);
        $this->package('two', ['path' => 'modules', 'priority' => 60]);
        $this->directory('vendor/acme/one/modules/Twin/app');
        $this->directory('vendor/acme/two/modules/Twin/app');

        $this->reload();

        $this->assertNull(Modules::find('Twin'));
        $this->assertStringContainsString('same priority (60) by pools [acme/one], [acme/two]', $this->registry()->diagnostics()[0]->message);
    }

    public function test_custom_pool_classes_are_supported(): void
    {
        TenantPool::$path = $this->directory('tenants');
        $this->write('tenants/Branding/app/Theme.php', '<?php namespace Tenant\\Modules\\Branding; class Theme {}');

        try {
            $this->reload(['pools' => ['local' => ['path' => $this->base.'/Modules', 'priority' => 100], 'tenant' => TenantPool::class]]);

            $this->assertSame('tenant', Modules::find('Branding')->pool());
            $this->assertTrue(class_exists('Tenant\\Modules\\Branding\\Theme'));
        } finally {
            TenantPool::$path = null;
        }
    }
}
