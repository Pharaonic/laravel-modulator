<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class DeleteCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->write('Modules/Shop/app/Cart.php', '<?php namespace Modules\\Shop; class Cart {}');
        $this->directory('Modules/Shop/Modules/Orders/app');
        $this->directory('Modules/Blog/app');
        $this->directory('Modules/Catalog/app');
        $this->define('Modules/Blog', ['dependencies' => ['Catalog']]);
        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => [[
            'name' => 'acme/kiosk',
            'install-path' => '../acme/kiosk',
            'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => 'modules']]]]],
        ]]]));
        $this->directory('vendor/acme/kiosk/modules/Kiosk/app');
        $this->directory('Modules/Kiosk/app');
        $this->reload();
    }

    public function test_dry_run_and_non_interactive_runs_delete_nothing(): void
    {
        [$status, $output] = $this->command('module:delete', ['module' => 'Blog', '--dry-run' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('Dry run', $output);

        [$status, $output] = $this->command('module:delete', ['module' => 'Blog']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('--force', $output);

        $this->assertDirectoryExists($this->base.'/Modules/Blog');
    }

    public function test_force_deletes_the_module(): void
    {
        $this->assertSame(0, $this->command('module:delete', ['module' => 'Blog', '--force' => true])[0]);

        $this->assertDirectoryDoesNotExist($this->base.'/Modules/Blog');
        $this->assertDirectoryExists($this->base.'/Modules');
        $this->assertSame([], glob($this->base.'/storage/framework/modulator/tmp/*') ?: []);

        $this->reload();
        $this->assertNull(Modules::find('Blog'));
    }

    public function test_the_confirmation_is_asked_on_interactive_consoles(): void
    {
        putenv('SHELL_INTERACTIVE=1');

        $this->artisan('module:delete', ['module' => 'Blog'])
            ->expectsQuestion('Delete ['.realpath($this->base.'/Modules/Blog').'] and everything in it?', false)
            ->assertExitCode(1);

        $this->assertDirectoryExists($this->base.'/Modules/Blog');
    }

    public function test_modules_with_dependents_or_children_are_kept(): void
    {
        [$status, $output] = $this->command('module:delete', ['module' => 'Catalog', '--force' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('[Blog] depend on it', $output);

        [$status, $output] = $this->command('module:delete', ['module' => 'Shop', '--force' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('--with-children', $output);

        $this->assertSame(0, $this->command('module:delete', ['module' => 'Shop', '--force' => true, '--with-children' => true])[0]);
        $this->assertDirectoryDoesNotExist($this->base.'/Modules/Shop');
    }

    public function test_package_code_is_never_deleted_and_a_local_override_falls_back_after_deletion(): void
    {
        $this->assertSame('local', Modules::find('Kiosk')->pool());

        [$status, $output] = $this->command('module:delete', ['module' => 'Kiosk', '--force' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('becomes active', $output);

        $this->reload();
        $this->assertSame('acme/kiosk', Modules::find('Kiosk')->pool());

        [$status, $output] = $this->command('module:delete', ['module' => 'Kiosk', '--force' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Remove the package with Composer', $output);
        $this->assertDirectoryExists($this->base.'/vendor/acme/kiosk/modules/Kiosk');
    }

    public function test_symlinked_modules_are_not_followed(): void
    {
        $outside = self::temporaryDirectory('modulator-outside');
        mkdir($outside.'/app');
        symlink($outside, $this->base.'/Modules/Linked');
        $this->reload();

        $this->assertSame(1, $this->command('module:delete', ['module' => 'Linked', '--force' => true])[0]);
        $this->assertDirectoryExists($outside.'/app');

        unlink($this->base.'/Modules/Linked');
        rmdir($outside.'/app');
        rmdir($outside);
    }
}
