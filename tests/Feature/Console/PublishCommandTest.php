<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Illuminate\Filesystem\Filesystem;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class PublishCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => [[
            'name' => 'acme/kiosk',
            'install-path' => '../acme/kiosk',
            'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => 'modules']]]]],
        ]]]));
        $this->write('vendor/acme/kiosk/modules/Kiosk/app/Screen.php', '<?php namespace Modules\\Kiosk; class Screen {}');
        $this->write('vendor/acme/kiosk/modules/Kiosk/routes/web.php', "<?php Route::get('kiosk', function () { return 'kiosk'; });");
        $this->write('vendor/acme/kiosk/modules/Kiosk/Modules/Printer/app/Driver.php', '<?php namespace Modules\\Kiosk\\Printer; class Driver {}');

        $this->reload();
    }

    private function local(string $path = ''): string
    {
        return $this->base.'/Modules/Kiosk'.($path === '' ? '' : '/'.$path);
    }

    public function test_publishing_copies_the_module_and_the_local_copy_wins(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk'])->assertExitCode(0);

        $this->assertFileExists($this->local('app/Screen.php'));
        $this->assertFileExists($this->local('routes/web.php'));
        $this->assertDirectoryDoesNotExist($this->local('Modules'), 'Children are not published unless asked.');
        $this->assertFileExists($this->base.'/vendor/acme/kiosk/modules/Kiosk/app/Screen.php', 'The package is never modified.');

        $this->reload();

        $this->assertSame('local', Modules::find('Kiosk')->pool());
        $this->assertSame('acme/kiosk', Modules::find('Kiosk/Printer')->pool());
        $this->assertSame('Kiosk', Modules::find('Kiosk')->id(), 'The identity is preserved.');
    }

    public function test_children_are_published_on_request(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk', '--with-children' => true])->assertExitCode(0);

        $this->assertFileExists($this->local('Modules/Printer/app/Driver.php'));

        $this->reload();
        $this->assertSame('local', Modules::find('Kiosk/Printer')->pool());
    }

    public function test_a_single_child_can_be_published_without_its_parent(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk/Printer'])->assertExitCode(0);

        $this->assertFileExists($this->local('Modules/Printer/app/Driver.php'));
        $this->assertDirectoryDoesNotExist($this->local('app'));

        $this->reload();
        $this->assertSame('acme/kiosk', Modules::find('Kiosk')->pool());
        $this->assertSame('local', Modules::find('Kiosk/Printer')->pool());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk', '--dry-run' => true])
            ->expectsOutput('Dry run: nothing was written.')
            ->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($this->local());
    }

    public function test_local_modifications_need_force_and_are_backed_up(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk'])->assertExitCode(0);
        $this->artisan('module:publish', ['module' => 'Kiosk'])->expectsOutput('The local copy already matches the package.')->assertExitCode(0);

        $this->write($this->local('app/Screen.php'), '<?php namespace Modules\\Kiosk; class Screen { /* customised */ }');
        $this->write($this->local('app/Extra.php'), '<?php namespace Modules\\Kiosk; class Extra {}');
        $this->write($this->local('Modules/Local/app/Thing.php'), '<?php namespace Modules\\Kiosk\\Local; class Thing {}');

        $this->artisan('module:publish', ['module' => 'Kiosk'])
            ->expectsOutput('Changes: 0 added, 1 changed, 1 removed (relative to the local copy).')
            ->expectsOutput('A local copy exists and differs from the package. Review the changes above, then run again with --force (the local copy is kept as a backup).')
            ->assertExitCode(1);
        $this->assertStringContainsString('customised', (string) file_get_contents($this->local('app/Screen.php')));

        $this->artisan('module:publish', ['module' => 'Kiosk', '--force' => true])->assertExitCode(0);

        $this->assertStringNotContainsString('customised', (string) file_get_contents($this->local('app/Screen.php')));
        $this->assertFileDoesNotExist($this->local('app/Extra.php'));
        $this->assertFileExists($this->local('Modules/Local/app/Thing.php'), 'Local child modules survive a forced publish.');

        $backups = glob($this->base.'/storage/framework/modulator/backups/kiosk-*') ?: [];
        $this->assertCount(1, $backups);
        $this->assertStringContainsString('customised', (string) file_get_contents($backups[0].'/app/Screen.php'));
        $this->assertSame([], glob($this->base.'/storage/framework/modulator/tmp/*') ?: [], 'The workspace is cleaned up.');
    }

    public function test_removing_the_local_override_restores_the_package_after_a_rebuild(): void
    {
        $this->artisan('module:publish', ['module' => 'Kiosk'])->assertExitCode(0);
        $this->reload();
        $this->assertSame('local', Modules::find('Kiosk')->pool());

        (new Filesystem)->deleteDirectory($this->local());
        $this->artisan('module:discover')->assertExitCode(0);
        $this->reload();

        $this->assertSame('acme/kiosk', Modules::find('Kiosk')->pool());
    }

    public function test_invalid_and_non_package_modules_are_refused(): void
    {
        $this->directory('Modules/Mine/app');
        $this->reload();

        $this->artisan('module:publish', ['module' => '../Kiosk'])->assertExitCode(1);
        $this->artisan('module:publish', ['module' => 'Ghost'])
            ->expectsOutput('Module [Ghost] is not provided by a package, so there is nothing to publish.')
            ->assertExitCode(1);
        $this->artisan('module:publish', ['module' => 'Mine'])
            ->expectsOutput('Module [Mine] is not provided by a package, so there is nothing to publish.')
            ->assertExitCode(1);
    }

    public function test_publishing_to_a_pool_with_another_namespace_root_is_refused(): void
    {
        $this->reload(['generation' => ['pool' => 'app', 'stubs' => null]]);

        $this->artisan('module:publish', ['module' => 'Kiosk'])
            ->expectsOutput('Pool [app] cannot receive published modules: it must be an application pool with the app/ layout.')
            ->assertExitCode(1);
    }
}
