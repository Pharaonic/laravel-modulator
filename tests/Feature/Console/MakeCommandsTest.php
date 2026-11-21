<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class MakeCommandsTest extends TestCase
{
    private function assertValidPhp(string $file): void
    {
        $this->assertFileExists($file);
        token_get_all((string) file_get_contents($file), TOKEN_PARSE);
    }

    public function test_make_creates_a_module_that_boots(): void
    {
        [$status, $output] = $this->command('module:make', ['module' => 'Shop']);

        $this->assertSame(0, $status, $output);

        foreach (['app/Providers/ShopServiceProvider.php', 'routes/web.php', 'routes/api.php', 'database/seeders/DatabaseSeeder.php'] as $file) {
            $this->assertValidPhp($this->base.'/Modules/Shop/'.$file);
        }

        foreach (['database/migrations', 'database/factories', 'resources/views', 'resources/lang', 'tests'] as $directory) {
            $this->assertDirectoryExists($this->base.'/Modules/Shop/'.$directory);
        }

        $this->assertSame([], glob($this->base.'/storage/framework/modulator/tmp/*') ?: [], 'The workspace is cleaned up.');

        $this->reload();

        $this->assertTrue(Modules::find('Shop')->isEnabled());
        $this->assertNotEmpty($this->app->getProviders('Modules\\Shop\\Providers\\ShopServiceProvider'));
        $this->assertTrue(class_exists('Modules\\Shop\\Database\\Seeders\\DatabaseSeeder'));
    }

    public function test_make_nests_children_in_the_parent_modules_folder(): void
    {
        $this->command('module:make', ['module' => 'Commerce']);
        $this->reload();

        $this->assertSame(0, $this->command('module:make', ['module' => 'Commerce/Checkout', '--plain' => true])[0]);
        $this->assertFileExists($this->base.'/Modules/Commerce/Modules/Checkout/app/Providers/CheckoutServiceProvider.php');
        $this->assertFileDoesNotExist($this->base.'/Modules/Commerce/Modules/Checkout/routes/web.php');

        $this->assertSame(0, $this->command('module:make', ['module' => 'Platform/Billing'])[0], 'A namespace group needs no module.');
        $this->assertFileExists($this->base.'/Modules/Platform/Billing/app/Providers/BillingServiceProvider.php');

        $this->reload();
        $this->assertSame('Commerce', Modules::find('Commerce/Checkout')->parent());
        $this->assertNull(Modules::find('Platform'));
    }

    public function test_make_refuses_existing_reserved_and_invalid_identities(): void
    {
        $this->command('module:make', ['module' => 'Shop']);
        $this->reload();

        $this->assertSame(1, $this->command('module:make', ['module' => 'Shop'])[0]);
        $this->assertSame(1, $this->command('module:make', ['module' => 'Shop/Database'])[0]);
        $this->assertSame(1, $this->command('module:make', ['module' => '../Evil'])[0]);
        $this->assertSame(1, $this->command('module:make', ['module' => 'shop'])[0]);
        $this->assertDirectoryDoesNotExist($this->base.'/Evil');
    }

    public function test_every_generator_writes_valid_php_in_the_module_namespace(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $expected = [
            'action' => 'app/Actions/Thing.php',
            'channel' => 'app/Broadcasting/Thing.php',
            'class' => 'app/Thing.php',
            'command' => 'app/Console/Commands/Thing.php',
            'config' => 'config/Thing.php',
            'controller' => 'app/Http/Controllers/Thing.php',
            'event' => 'app/Events/Thing.php',
            'exception' => 'app/Exceptions/Thing.php',
            'factory' => 'database/factories/Thing.php',
            'interface' => 'app/Contracts/Thing.php',
            'job' => 'app/Jobs/Thing.php',
            'job-middleware' => 'app/Jobs/Middleware/Thing.php',
            'listener' => 'app/Listeners/Thing.php',
            'mail' => 'app/Mail/Thing.php',
            'middleware' => 'app/Http/Middleware/Thing.php',
            'model' => 'app/Models/Thing.php',
            'notification' => 'app/Notifications/Thing.php',
            'observer' => 'app/Observers/Thing.php',
            'policy' => 'app/Policies/Thing.php',
            'provider' => 'app/Providers/Thing.php',
            'request' => 'app/Http/Requests/Thing.php',
            'resource' => 'app/Http/Resources/Thing.php',
            'rule' => 'app/Rules/Thing.php',
            'scope' => 'app/Models/Scopes/Thing.php',
            'seeder' => 'database/seeders/Thing.php',
            'service' => 'app/Services/Thing.php',
            'test' => 'tests/Feature/Thing.php',
            'trait' => 'app/Concerns/Thing.php',
            'view' => 'resources/views/Thing.blade.php',
        ];

        foreach ($expected as $type => $file) {
            [$status, $output] = $this->command('module:make:'.$type, ['module' => 'Shop', 'name' => 'Thing']);

            $this->assertSame(0, $status, $type.': '.$output);
            $this->assertValidPhp($this->base.'/Modules/Shop/'.$file);

            if (! in_array($type, ['config', 'view'], true) && ($type !== 'factory' || class_exists('Illuminate\\Database\\Eloquent\\Factories\\Factory'))) {
                $this->assertStringContainsString('namespace Modules\\Shop', (string) file_get_contents($this->base.'/Modules/Shop/'.$file), $type);
            }
        }

        $this->assertStringContainsString("'shop:thing'", (string) file_get_contents($this->base.'/Modules/Shop/app/Console/Commands/Thing.php'));
    }

    public function test_model_options_create_the_companions_in_the_module(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:model', ['module' => 'Shop', 'name' => 'Product', '--all' => true]);

        $this->assertSame(0, $status, $output);
        $this->assertFileExists($this->base.'/Modules/Shop/app/Models/Product.php');
        $this->assertFileExists($this->base.'/Modules/Shop/database/factories/ProductFactory.php');
        $this->assertFileExists($this->base.'/Modules/Shop/database/seeders/ProductSeeder.php');
        $this->assertFileExists($this->base.'/Modules/Shop/app/Policies/ProductPolicy.php');
        $this->assertStringContainsString('public function show(Product $product)', (string) file_get_contents($this->base.'/Modules/Shop/app/Http/Controllers/ProductController.php'));
        $this->assertCount(1, glob($this->base.'/Modules/Shop/database/migrations/*_create_products_table.php') ?: []);
    }

    public function test_existing_files_need_force(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $this->command('module:make:service', ['module' => 'Shop', 'name' => 'Billing/Invoicer']);
        $file = $this->base.'/Modules/Shop/app/Services/Billing/Invoicer.php';
        $this->assertStringContainsString('namespace Modules\\Shop\\Services\\Billing;', (string) file_get_contents($file));
        file_put_contents($file, 'custom');

        [$status, $output] = $this->command('module:make:service', ['module' => 'Shop', 'name' => 'Billing/Invoicer']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $output);
        $this->assertSame('custom', file_get_contents($file));

        $this->assertSame(0, $this->command('module:make:service', ['module' => 'Shop', 'name' => 'Billing/Invoicer', '--force' => true])[0]);
        $this->assertNotSame('custom', file_get_contents($file));
    }

    public function test_invalid_names_and_package_modules_are_refused(): void
    {
        $this->directory('Modules/Shop/app');
        $this->write('vendor/composer/installed.json', (string) json_encode(['packages' => [[
            'name' => 'acme/kiosk',
            'install-path' => '../acme/kiosk',
            'extra' => ['pharaonic' => ['modulator' => ['pools' => [['path' => 'modules']]]]],
        ]]]));
        $this->directory('vendor/acme/kiosk/modules/Kiosk/app');
        $this->reload();

        foreach (['class', '../Evil', 'Bad-Name', '1Thing'] as $name) {
            $this->assertSame(1, $this->command('module:make:model', ['module' => 'Shop', 'name' => $name])[0], $name);
        }

        [$status, $output] = $this->command('module:make:model', ['module' => 'Kiosk', 'name' => 'Screen']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('module:publish Kiosk', $output);
        $this->assertDirectoryDoesNotExist($this->base.'/vendor/acme/kiosk/modules/Kiosk/app/Models');

        $this->assertSame(1, $this->command('module:make:migration', ['module' => 'Kiosk', 'name' => 'create_screens_table'])[0]);
        $this->assertSame(1, $this->command('module:make', ['module' => 'Kiosk'])[0]);
    }

    public function test_custom_stubs_replace_the_package_stubs(): void
    {
        $this->directory('Modules/Shop/app');
        $this->write('stubs/modulator/service.stub', "<?php\n\nnamespace {{ namespace }};\n\nfinal class {{ class }} // custom for {{ module }}\n{\n}\n");
        $this->write('stubs/modulator/module/provider.stub', "<?php\n\nnamespace {{ namespace }}\\Providers;\n\nfinal class {{ name }}ServiceProvider extends \\Illuminate\\Support\\ServiceProvider // custom\n{\n}\n");
        $this->reload(['generation' => ['pool' => 'local', 'stubs' => $this->base.'/stubs/modulator']]);

        $this->command('module:make:service', ['module' => 'Shop', 'name' => 'Pricing']);
        $this->assertStringContainsString('// custom for Shop', (string) file_get_contents($this->base.'/Modules/Shop/app/Services/Pricing.php'));

        $this->command('module:make', ['module' => 'Blog']);
        $this->assertStringContainsString('// custom', (string) file_get_contents($this->base.'/Modules/Blog/app/Providers/BlogServiceProvider.php'));
        $this->assertFileExists($this->base.'/Modules/Blog/routes/web.php', 'Stubs without a custom copy come from the package.');
    }

    public function test_legacy_modules_get_the_1x_directories(): void
    {
        $this->directory('app/Modules/Blog/Providers');
        $this->reload();

        $this->command('module:make:trait', ['module' => 'Blog', 'name' => 'Sluggable']);
        $this->command('module:make:command', ['module' => 'Blog', 'name' => 'Publish']);
        $this->command('module:make:seeder', ['module' => 'Blog', 'name' => 'PostSeeder']);

        $this->assertStringContainsString('namespace App\\Modules\\Blog\\Traits;', (string) file_get_contents($this->base.'/app/Modules/Blog/Traits/Sluggable.php'));
        $this->assertFileExists($this->base.'/app/Modules/Blog/Commands/Publish.php');
        $this->assertStringContainsString('namespace App\\Modules\\Blog\\database\\seeders;', (string) file_get_contents($this->base.'/app/Modules/Blog/database/seeders/PostSeeder.php'));
    }

    public function test_generated_console_commands_are_registered(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();
        $this->command('module:make:command', ['module' => 'Shop', 'name' => 'SyncCommand']);
        $this->reload();

        $this->assertArrayHasKey('shop:sync', \Illuminate\Support\Facades\Artisan::all());
    }
}
