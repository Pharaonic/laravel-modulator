<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class ManifestCacheTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected $modulator = ['discovery' => ['cache' => true]];

    private function manifest(): string
    {
        return $this->base.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'modulator.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return require $this->manifest();
    }

    public function test_the_manifest_is_written_and_reused(): void
    {
        $this->write('Modules/Shop/app/Models/Item.php', '<?php namespace Modules\\Shop\\Models; class Item {}');

        $this->reload();

        $this->assertFileExists($this->manifest());
        $data = $this->data();
        $this->assertSame(1, $data['version']);
        $this->assertSame(['Modules\\Shop\\Models\\Item' => realpath($this->base.'/Modules/Shop/app/Models/Item.php')], $data['classmap']['Shop']);

        $before = filemtime($this->manifest());
        touch($this->manifest(), $before - 100);
        $this->reload();

        $this->assertSame($before - 100, filemtime($this->manifest()), 'A current manifest is not rewritten.');
    }

    public function test_a_new_module_directory_invalidates_the_manifest(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();
        $this->assertSame(['Shop'], array_keys(Modules::all()));

        $this->directory('Modules/Blog/app');
        touch($this->base.'/Modules', time() + 5);
        $this->reload();

        $this->assertSame(['Blog', 'Shop'], array_keys(Modules::all()));
    }

    public function test_changing_pools_invalidates_the_manifest(): void
    {
        $this->directory('Modules/Shop/app');
        $this->directory('extra/Blog/app');
        $this->reload();

        $this->reload(['pools' => [
            'local' => ['path' => $this->base.'/Modules', 'priority' => 100],
            'extra' => ['path' => $this->base.'/extra', 'priority' => 10],
        ]]);

        $this->assertSame(['Blog', 'Shop'], array_keys(Modules::all()));
    }

    public function test_state_changes_need_no_rediscovery(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();
        $before = $this->data()['signature'];

        $this->reload(['disabled' => ['Shop']]);

        $this->assertFalse(Modules::find('Shop')->isEnabled());
        $this->assertSame($before, $this->data()['signature']);
    }

    public function test_discover_cache_and_clear_commands(): void
    {
        $this->directory('Modules/Shop/app');
        $this->write('Modules/Shop/app/Models/Item.php', '<?php namespace Modules\\Shop\\Models; class Item {}');

        $this->artisan('module:discover')->expectsOutput('Module manifest written to '.$this->manifest().'.')->assertExitCode(0);
        $this->assertFileExists($this->manifest());

        $this->artisan('module:clear')->expectsOutput('Module manifest removed.')->assertExitCode(0);
        $this->assertFileDoesNotExist($this->manifest());
        $this->artisan('module:clear')->expectsOutput('No module manifest to remove.')->assertExitCode(0);

        $this->artisan('module:cache')->expectsOutput('Modules cached: 1 module(s), 1 class(es).')->assertExitCode(0);
        $this->assertFileExists($this->manifest());
    }

    public function test_module_cache_refuses_to_write_a_broken_tree(): void
    {
        $this->directory('Modules/Shop/app');
        $this->write('Modules/Orders/app/.keep');
        $this->write('Modules/Orders/module.php', "<?php return ['dependencies' => ['Catalog']];");

        $this->artisan('module:cache')
            ->expectsOutput('  ERROR  Module [Orders] is not enabled. Disabled because its dependency [Catalog] is missing.')
            ->expectsOutput('Module cache not written: 1 error(s). Fix them and run module:cache again.')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($this->manifest());
    }

    public function test_a_read_only_cache_directory_is_handled(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_getuid') && posix_getuid() === 0)) {
            $this->markTestSkipped('Directory permissions are not enforced here.');
        }

        $this->directory('Modules/Shop/app');
        @unlink($this->manifest());
        chmod($this->base.'/bootstrap/cache', 0555);

        try {
            $this->reload();
            $this->assertSame(['Shop'], array_keys(Modules::all()), 'Discovery still works in memory.');
            $this->assertFileDoesNotExist($this->manifest());

            $this->artisan('module:cache')->assertExitCode(1);
        } finally {
            chmod($this->base.'/bootstrap/cache', 0755);
        }
    }
}
