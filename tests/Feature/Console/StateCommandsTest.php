<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\ConfigEditor;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class StateCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->directory('Modules/Catalog/app');
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Shop/Modules/Orders/app');
        $this->define('Modules/Shop', ['dependencies' => ['Catalog']]);
        $this->directory('Modules/Blog/app');

        $this->reload();
    }

    private function config(): string
    {
        return $this->base.'/config/pharaonic/modulator.php';
    }

    /**
     * @return list<string>
     */
    private function disabled(): array
    {
        return (new ConfigEditor)->read((string) file_get_contents($this->config()));
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_octane_users_are_told_to_reload_it(): void
    {
        // This process only: the lowest Laravel 9 dependencies print PHP 8.1+
        // deprecations, which a separate process would report as a failure.
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        if (! class_exists('Laravel\\Octane\\Octane')) {
            eval('namespace Laravel\\Octane; final class Octane {}');
        }

        [$status, $output] = $this->command('module:disable', ['module' => 'Blog']);

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('"php artisan queue:restart" and "php artisan octane:reload"', $output);
    }

    public function test_disabling_publishes_the_config_and_keeps_its_comments(): void
    {
        [$status, $output] = $this->command('module:disable', ['module' => 'Blog']);

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('Disabled [Blog].', $output);
        $this->assertStringContainsString('php artisan queue:restart', $output);
        $this->assertStringNotContainsString('octane:reload', $output, 'Octane is not installed.');
        $this->assertSame(['Blog'], $this->disabled());
        $this->assertStringContainsString("// 'Analytics',", (string) file_get_contents($this->config()));

        $this->reload();
        $this->assertFalse(Modules::find('Blog')->isEnabled());

        [$status, $output] = $this->command('module:disable', ['module' => 'Blog']);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('already disabled', $output);
    }

    public function test_disabling_a_parent_disables_its_children_but_keeps_their_state(): void
    {
        [$status, $output] = $this->command('module:disable', ['module' => 'Shop']);

        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('[Shop/Orders]', $output);
        $this->assertSame(['Shop'], $this->disabled(), 'The child is not written to the config.');

        $this->reload();
        $this->assertFalse(Modules::find('Shop/Orders')->isEnabled());

        $this->command('module:enable', ['module' => 'Shop']);
        $this->reload();
        $this->assertTrue(Modules::find('Shop/Orders')->isEnabled());
    }

    public function test_disabling_a_dependency_fails_unless_cascading(): void
    {
        [$status, $output] = $this->command('module:disable', ['module' => 'Catalog']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Shop (Disabled because its dependency [Catalog] is disabled.)', $output);
        $this->assertStringContainsString('Nothing was changed', $output);
        $this->assertFileDoesNotExist($this->config());

        [$status, $output] = $this->command('module:disable', ['module' => 'Catalog', '--cascade' => true]);
        $this->assertSame(1, $status, 'A non-interactive cascade needs --force.');
        $this->assertFileDoesNotExist($this->config());

        [$status, $output] = $this->command('module:disable', ['module' => 'Catalog', '--cascade' => true, '--force' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertSame(['Catalog'], $this->disabled());

        $this->reload();
        $this->assertFalse(Modules::find('Shop')->isEnabled());
    }

    public function test_enabling_a_child_cannot_bypass_its_disabled_parent(): void
    {
        $this->write('config/pharaonic/modulator.php', "<?php\n\nreturn [\n    'disabled' => [\n        'Shop',\n        'Shop/Orders',\n    ],\n];\n");
        $this->reload();

        [$status, $output] = $this->command('module:enable', ['module' => 'Shop/Orders']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('cannot be enabled; nothing was changed', $output);
        $this->assertStringContainsString('Shop: Disabled in configuration', $output);
        $this->assertSame(['Shop', 'Shop/Orders'], $this->disabled());

        [$status, $output] = $this->command('module:enable', ['module' => 'Shop/Orders', '--with-parents' => true]);

        $this->assertSame(0, $status, $output);
        $this->assertSame([], $this->disabled());
    }

    public function test_enabling_can_enable_the_dependencies_on_request(): void
    {
        $this->write('config/pharaonic/modulator.php', "<?php return ['disabled' => ['Catalog', 'Shop']];");
        $this->reload();

        $this->assertSame(1, $this->command('module:enable', ['module' => 'Shop'])[0]);
        $this->assertSame(0, $this->command('module:enable', ['module' => 'Shop', '--with-dependencies' => true])[0]);
        $this->assertSame([], $this->disabled());

        [$status, $output] = $this->command('module:enable', ['module' => 'Shop']);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('already enabled', $output);
    }

    public function test_a_computed_list_is_refused_and_left_untouched(): void
    {
        $code = "<?php return ['disabled' => explode(',', (string) env('MODULES_DISABLED', ''))];";
        $this->write('config/pharaonic/modulator.php', $code);

        [$status, $output] = $this->command('module:disable', ['module' => 'Blog']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('cannot be edited safely', $output);
        $this->assertSame($code, file_get_contents($this->config()));
    }

    public function test_unknown_and_invalid_modules_are_rejected(): void
    {
        $this->assertSame(1, $this->command('module:disable', ['module' => 'Missing'])[0]);
        $this->assertSame(1, $this->command('module:disable', ['module' => '../Shop'])[0]);
        $this->assertSame(1, $this->command('module:enable', ['module' => 'shop'])[0]);
        $this->assertFileDoesNotExist($this->config());
    }

    public function test_production_needs_force(): void
    {
        $this->app['env'] = 'production';

        $this->assertSame(1, $this->command('module:disable', ['module' => 'Blog'])[0]);
        $this->assertFileDoesNotExist($this->config());
        $this->assertSame(0, $this->command('module:disable', ['module' => 'Blog', '--force' => true])[0]);
    }

    public function test_status_shows_the_configured_and_effective_state(): void
    {
        $this->write('config/pharaonic/modulator.php', "<?php return ['disabled' => ['Catalog']];");
        $this->reload();

        [$status, $output] = $this->command('module:status');
        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression('/Shop\s+\|\s+enabled\s+\|\s+disabled\s+\|\s+Disabled because its dependency \[Catalog\]/', $output);

        [$status, $output] = $this->command('module:status', ['module' => 'Shop/Orders']);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('Configured: enabled', $output);
        $this->assertStringContainsString('Effective:  disabled', $output);
        $this->assertStringContainsString('Shop/Orders: Disabled because its parent [Shop] is disabled.', $output);
        $this->assertStringContainsString('Catalog: Disabled in configuration', $output);
    }
}
